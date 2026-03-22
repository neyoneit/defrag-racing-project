<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\External\Q3DFRecordsApi;
use App\Models\User;
use App\Models\Map;
use App\Models\Record;
use App\Models\RecordHistory;
use App\Models\MddProfile;

use App\Jobs\ProcessNotificationsJob;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class GetLastMddRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    private int $duplicateCount = 0;
    private int $insertedCount = 0;
    private int $updatedCount = 0;

    public function __construct() {}

    public function handle(): void{
        $api = new Q3DFRecordsApi();

        $records = $api->getMddRecords();

        if (count($records) === 0) {
            echo ('No records found !') . PHP_EOL;
            return;
        }

        $this->duplicateCount = 0;
        $this->insertedCount = 0;
        $this->updatedCount = 0;

        $this->processRecords($records);

        echo ("Finished. Total: " . count($records) . ", New: {$this->insertedCount}, Updated: {$this->updatedCount}, Duplicates: {$this->duplicateCount}") . PHP_EOL;

        // Anomaly detection: if we got a large batch with 0 duplicates, records were likely missed
        $totalRecords = count($records);
        if ($totalRecords >= 20 && $this->duplicateCount === 0) {
            $this->sendGapAlert($totalRecords);
        }
    }

    private function sendGapAlert(int $batchSize): void
    {
        // Find the last record before this batch (by created_at)
        $lastRecord = Record::orderBy('created_at', 'desc')
            ->skip($this->insertedCount)
            ->first();

        $lastRecordInfo = $lastRecord
            ? "{$lastRecord->mapname} by {$lastRecord->name} ({$lastRecord->physics}) at {$lastRecord->date_set}"
            : 'Unknown';

        $message = "RECORD SCRAPER GAP DETECTED\n\n"
            . "Batch of {$batchSize} records had 0 duplicates - this means records were likely missed between scraper runs.\n\n"
            . "New records inserted: {$this->insertedCount}\n"
            . "Updated records: {$this->updatedCount}\n"
            . "Last known record before gap: {$lastRecordInfo}\n\n"
            . "Action needed: Run the HTML scraper for the missing period to fill the gap.\n"
            . "Time: " . now()->toDateTimeString();

        Log::warning('Record scraper gap detected', [
            'batch_size' => $batchSize,
            'duplicates' => 0,
            'inserted' => $this->insertedCount,
            'last_record' => $lastRecordInfo,
        ]);

        // Email all admins
        try {
            $adminEmails = User::where('admin', true)->pluck('email')->toArray();

            if (!empty($adminEmails)) {
                Mail::raw($message, function ($mail) use ($adminEmails) {
                    $mail->to($adminEmails)
                        ->subject('[Defrag Racing] Record Scraper Gap Detected - Records May Be Missing');
                });
                echo "Gap alert email sent to: " . implode(', ', $adminEmails) . PHP_EOL;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send gap alert email: ' . $e->getMessage());
            echo "Failed to send gap alert email: " . $e->getMessage() . PHP_EOL;
        }
    }

    private function processRecords($records) {
        foreach($records as $record) {
            $find = Record::query()
                    ->where('physics', $record['physics'])
                    ->where('mode', $record['mode'])
                    ->where('mdd_id', $record['mdd_id'])
                    ->where('mapname', $record['map'])->first();

            if (! $find) {
                $this->insertRecord($record);
                $this->insertedCount++;
                continue;
            }

            if ($find->time === $record['time']) {
                $this->duplicateCount++;
                echo ("Duplicate Found [" . $find->name . "] (" . $find->time . ") (" . $find->mapname . ") (" . $find->physics . ")") . PHP_EOL;
                continue;
            }

            if ($find->time !== $record['time']) {
                $this->insertHistoricRecord($find, $record);
                $this->updatedCount++;
            }

            $this->insertRecord($record);
            $this->insertedCount++;
        }
    }

    private function insertHistoricRecord($oldrecord, $newrecord) {
        $historic = new RecordHistory();
        $historic->fill($oldrecord->toArray());

        $historic->save();

        $oldrecord->delete();
    }

    private function insertRecord($record) {
        echo ("Inserting Record [" . $record['name'] . "] (" . $record['time'] . ") (" . $record['map'] . ") (" . $record['physics'] . ") (" . $record['mdd_id'] . ")") . PHP_EOL;

        $newrecord = new Record();

        $record['map'] = strtolower($record['map']);

        $newrecord->name = $record['name'];
        $newrecord->mapname = $record['map'];
        $newrecord->mdd_id = $record['mdd_id'];
        $newrecord->date_set = $record['date'];
        $newrecord->physics = $record['physics'];
        $newrecord->mode = $record['mode'];
        $newrecord->country = $record['country'];
        $newrecord->time = $record['time'];
        $newrecord->gametype = $record['mode'] . '_' . $record['physics'];

        $user = User::where('mdd_id', $record['mdd_id'])->first();

        if ($user) {
            $newrecord->user_id = $user->id;
        }

        $newrecord->save();

        $serverMap = Map::where('name', $record['map'])->first();

        if ($serverMap) {
            $serverMap->processRanks();
            $serverMap->processAverageTime();
        }

        $mdd_profile = MddProfile::where('id', $newrecord->mdd_id)->first();

        if ($mdd_profile) {
            $mdd_profile->processStats();
        } else {
            ScrapeProfile::dispatch($newrecord->mdd_id);
        }

        ProcessNotificationsJob::dispatch($newrecord);
    }

    private function get_int_parameter($name) {
        $param = $this->$name;

        return intval($param);
    }
}
