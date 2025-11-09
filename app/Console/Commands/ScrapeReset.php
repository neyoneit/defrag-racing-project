<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ScraperProgress;
use App\Models\ScrapedPage;
use App\Models\ScrapedRecordsQueue;

class ScrapeReset extends Command
{
    protected $signature = 'scrape:reset {--force : Skip confirmation}';
    protected $description = 'Reset scraper tables (clears progress, pages, and queue)';

    public function handle()
    {
        if (!$this->option('force')) {
            $this->warn("⚠️  WARNING: This will DELETE all scraper data:");
            $this->line("   - Scraper progress");
            $this->line("   - Scraped pages tracking");
            $this->line("   - Queued records");
            $this->newLine();
            $this->info("   (Your actual records table will NOT be affected)");
            $this->newLine();

            if (!$this->confirm('Are you sure you want to reset?')) {
                $this->info('Reset cancelled.');
                return 0;
            }
        }

        $this->info('Resetting scraper data...');

        // Get counts before deletion
        $progressCount = ScraperProgress::count();
        $pagesCount = ScrapedPage::count();
        $queueCount = ScrapedRecordsQueue::count();

        // Delete all scraper data
        ScrapedRecordsQueue::truncate();
        ScrapedPage::truncate();
        ScraperProgress::truncate();

        $this->newLine();
        $this->info("✓ Reset complete!");
        $this->line("   Deleted {$progressCount} progress record(s)");
        $this->line("   Deleted {$pagesCount} page(s)");
        $this->line("   Deleted {$queueCount} queued record(s)");
        $this->newLine();
        $this->info("You can now run: scrape:records-fetch-new");

        return 0;
    }
}
