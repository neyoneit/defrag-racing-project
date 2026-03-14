<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ScraperProgress;
use App\Models\ScrapedPage;
use App\Models\ScrapedRecordsQueue;

class ScrapeStatus extends Command
{
    protected $signature = 'scrape:status';
    protected $description = 'Show scraper progress and queue status';

    public function handle()
    {
        $progress = ScraperProgress::first();

        if (!$progress) {
            $this->warn("No scraper progress found. Run scrape:records-fetch-new to start scraping.");
            return 0;
        }

        // Queue stats
        $pending = ScrapedRecordsQueue::where('status', 'pending')->count();
        $processing = ScrapedRecordsQueue::where('status', 'processing')->count();
        $completed = ScrapedRecordsQueue::where('status', 'completed')->count();
        $failed = ScrapedRecordsQueue::where('status', 'failed')->count();

        // Page stats
        $pagesScraped = ScrapedPage::where('status', 'queued')->count();
        $pagesProcessed = ScrapedPage::where('status', 'processed')->count();
        $totalPages = ScrapedPage::count();

        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════");
        $this->info("                  SCRAPER STATUS");
        $this->info("═══════════════════════════════════════════════════════");
        $this->newLine();

        $this->info("📊 SCRAPER PROGRESS:");
        $this->line("   Status: " . strtoupper($progress->status));
        $this->line("   Current Page: " . number_format($progress->current_page));
        if ($progress->detected_last_page) {
            $this->line("   Detected Last Page: " . number_format($progress->detected_last_page));
            $percentComplete = round(($progress->current_page / $progress->detected_last_page) * 100, 2);
            $this->line("   Progress: {$percentComplete}%");
        }
        $this->line("   Total Records Scraped: " . number_format($progress->records_scraped));
        if ($progress->last_scrape_at) {
            $this->line("   Last Scrape: " . $progress->last_scrape_at->diffForHumans());
        }
        $this->newLine();

        if ($progress->stop_reason) {
            $this->info("🛑 STOP REASON:");
            $this->line("   " . $progress->stop_reason);
            $this->newLine();
        }

        if ($progress->error_message) {
            $this->error("❌ ERROR:");
            $this->line("   " . $progress->error_message);
            $this->newLine();
        }

        $this->info("📄 PAGES:");
        $this->line("   Total Pages: " . number_format($totalPages));
        $this->line("   Scraped (queued): " . number_format($pagesScraped));
        $this->line("   Processed: " . number_format($pagesProcessed));
        $this->newLine();

        $this->info("📦 QUEUE STATUS:");
        $this->line("   Pending: " . number_format($pending));
        $this->line("   Processing: " . number_format($processing));
        $this->line("   Completed: " . number_format($completed));
        $this->line("   Failed: " . number_format($failed));
        $this->newLine();

        $total = $pending + $processing + $completed + $failed;
        if ($total > 0) {
            $completedPercent = round(($completed / $total) * 100, 2);
            $this->info("   Processing Progress: {$completedPercent}% ({$completed}/{$total})");
            $this->newLine();
        }

        $this->info("═══════════════════════════════════════════════════════");
        $this->newLine();

        // Show recent failed records if any
        if ($failed > 0) {
            $this->warn("Recent Failed Records:");
            $failedRecords = ScrapedRecordsQueue::where('status', 'failed')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($failedRecords as $fr) {
                $this->line("   Page {$fr->page_number}, Index {$fr->record_index}: {$fr->error_message}");
            }
            $this->newLine();
        }

        return 0;
    }
}
