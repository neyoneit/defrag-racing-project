<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use Illuminate\Console\Command;

class CompressFailedDemos extends Command
{
    protected $signature = 'demos:compress-failed {--dry-run : Show what would be compressed without doing it}';
    protected $description = 'Retroactively compress failed/unsupported demo files from dm_68 to 7z';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $format = config('app.demo_compression_format', '7z');

        $demos = UploadedDemo::whereIn('status', ['failed', 'failed-validity', 'unsupported-version'])
            ->where('file_path', 'like', '%.dm_%')
            ->get();

        $this->info("Found {$demos->count()} uncompressed failed demos.");

        if ($dryRun) {
            $this->info('Dry run - no changes will be made.');
            return 0;
        }

        $bar = $this->output->createProgressBar($demos->count());
        $bar->start();

        $compressed = 0;
        $missing = 0;
        $errors = 0;
        $savedBytes = 0;

        foreach ($demos as $demo) {
            $sourcePath = storage_path('app/' . $demo->file_path);

            if (!file_exists($sourcePath)) {
                $missing++;
                $bar->advance();
                continue;
            }

            try {
                $originalSize = filesize($sourcePath);
                $compressedFilename = pathinfo(basename($sourcePath), PATHINFO_FILENAME) . '.' . $format;
                $compressedPath = dirname($sourcePath) . '/' . $compressedFilename;

                // Compress with 7z
                $escapedSource = escapeshellarg($sourcePath);
                $escapedOutput = escapeshellarg($compressedPath);

                exec("7z a -t7z -mx=5 -mmt=4 $escapedOutput $escapedSource 2>&1", $output, $returnCode);

                if ($returnCode !== 0) {
                    $errors++;
                    $bar->advance();
                    continue;
                }

                // Rename file inside archive to match original filename
                $escapedOriginalBase = escapeshellarg(basename($sourcePath));
                $escapedOriginalName = escapeshellarg($demo->original_filename);
                exec("7z rn $escapedOutput $escapedOriginalBase $escapedOriginalName 2>&1");

                $compressedSize = filesize($compressedPath);
                $savedBytes += ($originalSize - $compressedSize);

                // Remove original dm_68
                unlink($sourcePath);

                // Update DB
                $failedDir = "demos/failed/{$demo->id}";
                $demo->update([
                    'file_path' => $failedDir . '/' . $compressedFilename,
                    'processed_filename' => $compressedFilename,
                ]);

                $compressed++;
            } catch (\Exception $e) {
                $errors++;
                $this->error("\nError on demo {$demo->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $savedMB = round($savedBytes / 1024 / 1024, 1);
        $this->info("Done! Compressed: {$compressed}, Missing files: {$missing}, Errors: {$errors}");
        $this->info("Disk space saved: {$savedMB} MB");

        return 0;
    }
}
