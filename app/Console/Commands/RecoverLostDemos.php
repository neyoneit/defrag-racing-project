<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UploadedDemo;
use Illuminate\Support\Facades\Storage;

class RecoverLostDemos extends Command
{
    protected $signature = 'demos:recover-lost {source_dir} {--dry-run : Only check MD5 matches, don\'t compress or upload} {--output-dir= : Directory to write 7z files (default: same as source_dir)}';
    protected $description = 'Recover processed demos with empty file_path by matching MD5 hashes, compressing to 7z, and preparing for Backblaze upload';

    public function handle()
    {
        $sourceDir = rtrim($this->argument('source_dir'), '/');
        $dryRun = $this->option('dry-run');
        $outputDir = $this->option('output-dir') ?: $sourceDir . '/7z_output';

        if (!is_dir($sourceDir)) {
            $this->error("Source directory not found: $sourceDir");
            return 1;
        }

        // Create output dir for 7z files
        if (!$dryRun && !is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Load all processed demos with empty file_path, indexed by file_hash
        $demos = UploadedDemo::where('status', 'processed')
            ->where('file_path', '')
            ->whereNotNull('file_hash')
            ->where('file_hash', '!=', '')
            ->get();

        $hashIndex = [];
        foreach ($demos as $demo) {
            $hashIndex[$demo->file_hash] = $demo;
        }

        $this->info("DB: {$demos->count()} processed demos with empty file_path");
        $this->info("Source: $sourceDir");

        // Scan source directory
        $files = glob($sourceDir . '/*.dm_*');
        $this->info("Files on disk: " . count($files));

        $matched = 0;
        $mismatched = 0;
        $compressed = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $filePath) {
            $md5 = md5_file($filePath);
            $filename = basename($filePath);

            if (isset($hashIndex[$md5])) {
                $demo = $hashIndex[$md5];
                $matched++;

                if (!$dryRun) {
                    // Compress to 7z with processed_filename
                    $processedFilename = $demo->processed_filename;
                    $sevenZFilename = pathinfo($processedFilename, PATHINFO_FILENAME) . '.7z';

                    // If processed_filename already ends with .7z, use it as-is
                    if (str_ends_with($processedFilename, '.7z')) {
                        $sevenZFilename = $processedFilename;
                    }

                    $outputPath = $outputDir . '/' . $sevenZFilename;

                    // Skip if already compressed - but still upload
                    if (file_exists($outputPath)) {
                        $backblazePath = 'demos/' . $sevenZFilename;
                        try {
                            if (!Storage::exists($backblazePath)) {
                                Storage::put($backblazePath, file_get_contents($outputPath));
                            }
                        } catch (\Exception $e) {
                            $this->newLine();
                            $this->error("Failed to upload (existing): $sevenZFilename - " . $e->getMessage());
                            $errors++;
                            $bar->advance();
                            continue;
                        }
                        $demo->file_path = $backblazePath;
                        $demo->save();
                        $compressed++;
                        $bar->advance();
                        continue;
                    }

                    // Compress
                    $escapedInput = escapeshellarg($filePath);
                    $escapedOutput = escapeshellarg($outputPath);
                    $escapedProcessed = escapeshellarg($processedFilename);

                    exec("7z a -t7z -mx=5 -mmt=4 $escapedOutput $escapedInput 2>&1", $output, $returnCode);

                    if ($returnCode !== 0) {
                        $this->newLine();
                        $this->error("Failed to compress: $filename - " . implode("\n", $output));
                        $errors++;
                        $bar->advance();
                        continue;
                    }

                    // Rename file inside archive to processed_filename (without .7z)
                    $innerName = str_ends_with($processedFilename, '.7z')
                        ? pathinfo($processedFilename, PATHINFO_FILENAME)
                        : $processedFilename;
                    exec("7z rn $escapedOutput " . escapeshellarg($filename) . " " . escapeshellarg($innerName) . " 2>&1");

                    // Upload to Backblaze
                    $backblazePath = 'demos/' . $sevenZFilename;
                    try {
                        Storage::put($backblazePath, file_get_contents($outputPath));
                    } catch (\Exception $e) {
                        $this->newLine();
                        $this->error("Failed to upload: $sevenZFilename - " . $e->getMessage());
                        $errors++;
                        $bar->advance();
                        continue;
                    }

                    // Update DB
                    $demo->file_path = $backblazePath;
                    $demo->save();
                    $compressed++;
                }
            } else {
                $mismatched++;
                if ($dryRun) {
                    $this->newLine();
                    $this->warn("NO MATCH: $filename (md5: $md5)");
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("=== RESULTS ===");
        $this->info("MD5 matched: $matched");
        $this->info("No match: $mismatched");
        if (!$dryRun) {
            $this->info("Compressed: $compressed");
            $this->info("Errors: $errors");
            $this->info("7z files in: $outputDir");
            $this->newLine();
            $this->info("Next steps:");
            $this->info("1. Upload contents of $outputDir to Backblaze bucket 'defrag-demos' under 'demos/' prefix");
            $this->info("2. On production, run the same DB updates (export file_path updates)");
        }

        return 0;
    }
}
