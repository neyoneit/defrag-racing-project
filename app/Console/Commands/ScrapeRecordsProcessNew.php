<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ScrapedRecordsQueue;
use App\Models\ScrapedPage;
use App\Models\Record;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\Map;
use App\Models\MddProfile;

class ScrapeRecordsProcessNew extends Command
{
    protected $signature = 'scrape:records-process-new
                            {--batch-size=100 : Number of records to process per batch}
                            {--continuous : Run continuously (loop forever)}';

    protected $description = 'Process scraped records from queue and insert into database';

    public function handle()
    {
        $batchSize = (int) $this->option('batch-size');
        $continuous = $this->option('continuous');

        $this->info("Starting record processor (batch size: {$batchSize})");
        if ($continuous) {
            $this->info("Running in CONTINUOUS mode (will loop forever)");
        }
        $this->newLine();

        $totalProcessed = 0;
        $totalInserted = 0;
        $totalDuplicates = 0;
        $totalUpdated = 0;
        $totalFailed = 0;

        do {
            // Get next batch of pending records
            $queuedRecords = ScrapedRecordsQueue::where('status', 'pending')
                ->orWhere(function($q) {
                    $q->where('status', 'failed')
                      ->where('retry_count', '<', 3);
                })
                ->orderBy('page_number')
                ->orderBy('record_index')
                ->limit($batchSize)
                ->get();

            if ($queuedRecords->isEmpty()) {
                if ($continuous) {
                    $this->info("No pending records, waiting 5 seconds...");
                    sleep(5);
                    continue;
                } else {
                    $this->info("No pending records. Exiting.");
                    break;
                }
            }

            $this->info("Processing batch of {$queuedRecords->count()} records...");

            foreach ($queuedRecords as $queueItem) {
                try {
                    $queueItem->update(['status' => 'processing']);

                    $record = $queueItem->record_data;

                    // Duplicate check (IDEMPOTENT)
                    $existing = Record::where('physics', $record['physics'])
                        ->where('mode', $record['mode'])
                        ->where('mdd_id', $record['mdd_id'])
                        ->where('mapname', strtolower($record['map']))
                        ->first();

                    if ($existing && $existing->time === $record['time']) {
                        // Exact duplicate - skip
                        $totalDuplicates++;
                        $queueItem->update(['status' => 'completed']);
                        continue;
                    }

                    if ($existing && $existing->time !== $record['time']) {
                        // Time changed - move to history
                        $historic = new RecordHistory();
                        $historic->fill($existing->toArray());
                        $historic->save();
                        $existing->delete();
                        $totalUpdated++;
                    }

                    // Insert new record
                    $this->insertRecord($record);
                    $totalInserted++;
                    $queueItem->update(['status' => 'completed']);
                    $totalProcessed++;

                } catch (\Exception $e) {
                    $queueItem->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'retry_count' => $queueItem->retry_count + 1,
                    ]);
                    $totalFailed++;
                    $this->error("Error processing record (page {$queueItem->page_number}, index {$queueItem->record_index}): " . $e->getMessage());
                }
            }

            // Update page status if all records from a page are completed
            $this->updatePageStatus();

            $this->info("Batch complete: {$totalProcessed} processed, {$totalInserted} inserted, {$totalDuplicates} duplicates, {$totalUpdated} updated, {$totalFailed} failed");
            $this->newLine();

        } while ($continuous);

        $this->info("========================================");
        $this->info("PROCESSING COMPLETE");
        $this->info("========================================");
        $this->info("Total Processed: {$totalProcessed}");
        $this->info("Inserted: {$totalInserted}");
        $this->info("Duplicates: {$totalDuplicates}");
        $this->info("Updated: {$totalUpdated}");
        $this->info("Failed: {$totalFailed}");
        $this->info("========================================");

        return 0;
    }

    private function insertRecord($record)
    {
        $newrecord = new Record();

        $record['map'] = strtolower($record['map']);

        // Derive gametype from mode and physics (e.g., run_cpm, run_vq3)
        $gametype = $record['mode'] . '_' . $record['physics'];

        $newrecord->name = $record['name'];
        $newrecord->country = $record['country'];
        $newrecord->time = $record['time'];
        $newrecord->date_set = $record['date'];
        $newrecord->physics = $record['physics'];
        $newrecord->mode = $record['mode'];
        $newrecord->gametype = $gametype;
        $newrecord->mdd_id = $record['mdd_id'];
        $newrecord->mapname = $record['map'];

        // User association
        $user = User::where('mdd_id', $record['mdd_id'])->first();
        $newrecord->user_id = $user?->id;

        $newrecord->save();
    }

    private function updatePageStatus()
    {
        $pages = ScrapedPage::where('status', 'queued')->get();

        foreach ($pages as $page) {
            $pendingCount = ScrapedRecordsQueue::where('page_number', $page->page_number)
                ->whereIn('status', ['pending', 'processing'])
                ->count();

            if ($pendingCount === 0) {
                $page->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
            }
        }
    }
}
