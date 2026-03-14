<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\External\Q3DFRecords;
use App\Models\ScraperProgress;
use App\Models\ScrapedPage;
use App\Models\ScrapedRecordsQueue;

class ScrapeRecordsFetchNew extends Command
{
    protected $signature = 'scrape:records-fetch-new
                            {--from-page=1 : Start from specific page}
                            {--same-page-threshold=3 : How many times must see same page to confirm end}';

    protected $description = 'Fetch records from Q3DF with duplicate page detection';

    public function handle()
    {
        $startPage = (int) $this->option('from-page');
        $samePageThreshold = (int) $this->option('same-page-threshold');

        $progress = ScraperProgress::firstOrCreate([]);

        // Resume from last position if crashed
        if ($progress->status === 'error' || $progress->status === 'paused') {
            $startPage = $progress->current_page;
            $this->warn("Resuming from page {$startPage} (previous status: {$progress->status})");
        }

        $progress->update([
            'status' => 'running',
            'current_page' => $startPage,
            'error_message' => null,
            'stop_reason' => null,
        ]);

        $this->info("Starting scraper from page {$startPage}...");
        $this->info("Will stop after seeing same page content {$samePageThreshold} times");
        $this->newLine();

        $currentPage = $startPage;
        $lastPageFingerprint = null;
        $samePageCount = 0;
        $totalRecordsScraped = $progress->records_scraped;

        $scraper = new Q3DFRecords();

        try {
            while (true) {
                $this->info("[Page {$currentPage}] Scraping...");

                // Check if already scraped and processed
                $existingPage = ScrapedPage::where('page_number', $currentPage)->first();
                if ($existingPage && $existingPage->status === 'processed') {
                    $this->warn("[Page {$currentPage}] Already processed, skipping");
                    $currentPage++;
                    continue;
                }

                // Scrape the page
                $records = $scraper->scrape($currentPage);

                if (empty($records)) {
                    $this->warn("[Page {$currentPage}] No records found");
                    break;
                }

                // Generate fingerprint of this page
                $pageFingerprint = md5(json_encode($records));

                // Check if this is the same as previous page
                if ($pageFingerprint === $lastPageFingerprint) {
                    $samePageCount++;
                    $this->warn("[Page {$currentPage}] DUPLICATE CONTENT detected! (count: {$samePageCount}/{$samePageThreshold})");

                    if ($samePageCount >= $samePageThreshold) {
                        $actualLastPage = $currentPage - $samePageThreshold;
                        $stopReason = "Detected same page content {$samePageThreshold} times consecutively. Actual last page: {$actualLastPage}";

                        $this->newLine();
                        $this->info("========================================");
                        $this->info("SCRAPER STOPPED");
                        $this->info("========================================");
                        $this->info("Reason: {$stopReason}");
                        $this->info("Last valid page: {$actualLastPage}");
                        $this->info("Total records scraped: {$totalRecordsScraped}");
                        $this->info("========================================");

                        $progress->update([
                            'status' => 'completed',
                            'detected_last_page' => $actualLastPage,
                            'stop_reason' => $stopReason,
                        ]);

                        break;
                    }
                } else {
                    // New content found, reset counter
                    if ($samePageCount > 0) {
                        $this->info("[Page {$currentPage}] Content changed, resetting duplicate counter");
                    }
                    $samePageCount = 0;
                    $lastPageFingerprint = $pageFingerprint;

                    // Queue all records from this page
                    $queuedCount = 0;
                    foreach ($records as $index => $record) {
                        $created = ScrapedRecordsQueue::updateOrCreate(
                            [
                                'page_number' => $currentPage,
                                'record_index' => $index,
                            ],
                            [
                                'record_data' => $record,
                                'status' => 'pending',
                                'retry_count' => 0,
                            ]
                        );

                        if ($created->wasRecentlyCreated) {
                            $queuedCount++;
                        }
                    }

                    // Mark page as scraped
                    ScrapedPage::updateOrCreate(
                        ['page_number' => $currentPage],
                        [
                            'records_count' => count($records),
                            'page_fingerprint' => $pageFingerprint,
                            'status' => 'queued',
                            'scraped_at' => now(),
                        ]
                    );

                    $totalRecordsScraped += $queuedCount;

                    $recordsCount = count($records);
                    $duplicatesCount = $recordsCount - $queuedCount;
                    $this->info("[Page {$currentPage}] ✓ Scraped {$recordsCount} records ({$queuedCount} new, {$duplicatesCount} duplicates)");

                    // Update progress
                    $progress->update([
                        'current_page' => $currentPage,
                        'records_scraped' => $totalRecordsScraped,
                        'last_scrape_at' => now(),
                    ]);
                }

                $currentPage++;
                usleep(500000); // 0.5s delay between pages
            }

            return 0;

        } catch (\Exception $e) {
            $progress->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            $this->error("Error on page {$currentPage}: " . $e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }
    }
}
