<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use Illuminate\Console\Command;

class CompressUncompressedDemos extends Command
{
    protected $signature = 'demos:cleanup-orphaned-dm68 {--dry-run : Show what would be done without doing it}';
    protected $description = 'Delete orphaned .dm_68 files that have no DB reference (already processed to .7z)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $demosPath = storage_path('app/demos/');
        // glob() doesn't work with [ ] in filenames, use scandir + filter
        $allFiles = scandir($demosPath);
        $files = [];
        foreach ($allFiles as $f) {
            if (str_ends_with($f, '.dm_68')) {
                $files[] = $demosPath . $f;
            }
        }

        $this->info("Found " . count($files) . " .dm_68 files in demos/" . ($dryRun ? ' (dry run)' : ''));

        if (count($files) === 0) {
            $this->info('Nothing to clean up.');
            return;
        }

        $orphaned = 0;
        $referenced = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            $relativePath = 'demos/' . basename($file);

            // Check if this exact path is referenced in DB
            $inDb = UploadedDemo::where('file_path', $relativePath)->exists();

            if ($inDb) {
                $referenced++;
                $this->warn("Referenced in DB (skipping): " . basename($file));
                continue;
            }

            $size = filesize($file);
            $totalSize += $size;
            $orphaned++;

            if ($dryRun) {
                $this->line("Orphaned: " . basename($file) . " (" . round($size / 1024) . " KB)");
            } else {
                unlink($file);
            }
        }

        $this->newLine();
        $this->info("Orphaned: {$orphaned}, Referenced (kept): {$referenced}");
        $this->info("Space to free: " . round($totalSize / 1024 / 1024, 1) . " MB");

        if ($dryRun) {
            $this->warn('Run without --dry-run to delete orphaned files.');
        } else {
            $this->info("Deleted {$orphaned} orphaned .dm_68 files.");
        }
    }
}
