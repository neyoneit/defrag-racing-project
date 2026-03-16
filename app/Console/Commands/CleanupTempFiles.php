<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupTempFiles extends Command
{
    protected $signature = 'demos:cleanup-temp {--dry-run : Show what would be deleted without deleting}';
    protected $description = 'Delete orphaned temp_*.7z files from storage/app/';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $pattern = storage_path('app/temp_*.7z');
        $files = glob($pattern);

        $this->info("Found " . count($files) . " temp files" . ($dryRun ? ' (dry run)' : ''));

        if (count($files) === 0) {
            $this->info('Nothing to clean up.');
            return;
        }

        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }

        $this->info("Total size: " . round($totalSize / 1024 / 1024, 1) . " MB");

        if ($dryRun) {
            $this->warn('Run without --dry-run to delete.');
            return;
        }

        if (!$this->confirm("Delete " . count($files) . " temp files?")) {
            $this->info('Cancelled.');
            return;
        }

        $deleted = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} temp files, freed " . round($totalSize / 1024 / 1024, 1) . " MB");
    }
}
