<?php

// NOTE: This command is currently NOT usable for bulk fetching.
// The q3df.org getRecords API does not support pagination (page 2+ returns 401).
// Keeping this for future use — the API may be opened up later.
// For now, use scrape:records-fetch-new (HTML scraper) instead.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Record;
use App\Models\RecordHistory;
use App\Models\User;
use Carbon\Carbon;

class ScrapeRecordsApi extends Command
{
    protected $signature = 'scrape:records-api
                            {--from-page=1 : Start from specific page}
                            {--per-page=100 : Records per page (max 100)}
                            {--delay=300 : Delay between pages in milliseconds}
                            {--dry-run : Only show what would be done}';

    protected $description = 'Fetch all records from Q3DF API (getRecords) and insert missing ones';

    private int $totalInserted = 0;
    private int $totalDuplicates = 0;
    private int $totalUpdated = 0;
    private int $totalFailed = 0;

    public function handle()
    {
        $currentPage = (int) $this->option('from-page');
        $perPage = min((int) $this->option('per-page'), 100);
        $delay = (int) $this->option('delay');
        $dryRun = $this->option('dry-run');

        // First, get total count
        $this->info('Fetching total record count from Q3DF API...');
        $firstResponse = $this->fetchPage(1, 1);
        if (!$firstResponse) {
            $this->error('Failed to connect to Q3DF API.');
            return 1;
        }

        $totalRecords = $firstResponse['count'] ?? 0;
        $totalPages = (int) ceil($totalRecords / $perPage);

        $dbCount = Record::count();

        $this->info("Q3DF total records: {$totalRecords}");
        $this->info("Database records:   {$dbCount}");
        $this->info("Estimated missing:  " . ($totalRecords - $dbCount));
        $this->info("Pages to scan:      {$totalPages} (starting from page {$currentPage})");
        $this->info("Per page:           {$perPage}");
        $this->info("Delay:              {$delay}ms");
        if ($dryRun) {
            $this->warn('DRY RUN - no records will be inserted');
        }
        $this->newLine();

        $emptyPages = 0;
        $startTime = microtime(true);

        while ($currentPage <= $totalPages) {
            $data = $this->fetchPage($currentPage, $perPage);

            if (!$data || empty($data['data'])) {
                $emptyPages++;
                $this->warn("[Page {$currentPage}] Empty response (attempt {$emptyPages}/5)");

                if ($emptyPages >= 5) {
                    $this->error('Too many empty pages in a row, stopping.');
                    break;
                }

                $currentPage++;
                usleep($delay * 1000);
                continue;
            }

            $emptyPages = 0;
            $records = $data['data'];
            $pageBefore = $this->totalInserted;

            if (!$dryRun) {
                foreach ($records as $apiRecord) {
                    $this->processRecord($apiRecord);
                }
            }

            $pageInserted = $this->totalInserted - $pageBefore;
            $elapsed = round(microtime(true) - $startTime, 1);
            $pagesLeft = $totalPages - $currentPage;
            $pagesPerSec = $currentPage > 1 ? ($currentPage - (int) $this->option('from-page') + 1) / $elapsed : 0;
            $eta = $pagesPerSec > 0 ? round($pagesLeft / $pagesPerSec / 60, 1) : '?';

            $this->info("[Page {$currentPage}/{$totalPages}] +{$pageInserted} new | Total: {$this->totalInserted} inserted, {$this->totalDuplicates} dup, {$this->totalUpdated} upd | ETA: {$eta}min");

            $currentPage++;
            usleep($delay * 1000);
        }

        $elapsed = round(microtime(true) - $startTime, 1);

        $this->newLine();
        $this->info("========================================");
        $this->info("COMPLETE ({$elapsed}s)");
        $this->info("========================================");
        $this->info("Inserted:   {$this->totalInserted}");
        $this->info("Duplicates: {$this->totalDuplicates}");
        $this->info("Updated:    {$this->totalUpdated}");
        $this->info("Failed:     {$this->totalFailed}");
        $this->info("========================================");

        return 0;
    }

    private function fetchPage(int $page, int $perPage): ?array
    {
        $url = "https://q3df.org/api/getRecords?page={$page}&per_page={$perPage}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        return json_decode($response, true);
    }

    private function processRecord(array $apiRecord): void
    {
        try {
            $record = $this->mapApiRecord($apiRecord);

            // Check for exact duplicate (same player, map, physics, mode, time)
            $existing = Record::where('physics', $record['physics'])
                ->where('mode', $record['mode'])
                ->where('mdd_id', $record['mdd_id'])
                ->where('mapname', $record['map'])
                ->where('time', $record['time'])
                ->first();

            if ($existing) {
                $this->totalDuplicates++;
                return;
            }

            // Check if player has existing record with different time (improvement)
            $existingDifferentTime = Record::where('physics', $record['physics'])
                ->where('mode', $record['mode'])
                ->where('mdd_id', $record['mdd_id'])
                ->where('mapname', $record['map'])
                ->where('time', '!=', $record['time'])
                ->first();

            if ($existingDifferentTime) {
                $historic = new RecordHistory();
                $historic->fill($existingDifferentTime->toArray());
                $historic->save();
                $existingDifferentTime->delete();
                $this->totalUpdated++;
            }

            // Insert new record
            try {
                $this->insertRecord($record);
                $this->totalInserted++;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $this->totalDuplicates++;
                } else {
                    throw $e;
                }
            }
        } catch (\Exception $e) {
            $this->totalFailed++;
        }
    }

    private function mapApiRecord(array $apiRecord): array
    {
        $date = Carbon::createFromTimestamp($apiRecord['UnixTimestamp'], 'Europe/Berlin');
        $country = $apiRecord['User']['Country'] ?? '_404';

        if ($country === 'nocountry' || !$country) {
            $country = '_404';
        }

        $physics = trim($apiRecord['GameType']);
        $physicsParts = explode('-', $physics);

        return [
            'name' => $apiRecord['User']['Visname'],
            'country' => strtoupper($country),
            'mdd_id' => $apiRecord['User']['Id'],
            'time' => $apiRecord['MsTime'],
            'map' => strtolower(trim($apiRecord['Map'])),
            'physics' => $physicsParts[0],
            'mode' => $physicsParts[1],
            'date' => $date->toDateTimeString(),
        ];
    }

    private function insertRecord(array $record): void
    {
        $newrecord = new Record();

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
        $newrecord->user_id = $user?->id;

        $newrecord->save();
    }
}
