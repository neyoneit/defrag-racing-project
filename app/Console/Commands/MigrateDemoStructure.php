<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\UploadedDemo;

class MigrateDemoStructure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demos:migrate-structure {--dry-run : Show what would be migrated without actually moving files} {--batch=100 : Number of demos to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing demos to hierarchical directory structure for better performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch', 100);

        $this->info('Starting demo structure migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be moved');
        }

        $totalDemos = UploadedDemo::count();
        $this->info("Found {$totalDemos} demos to check");

        $bar = $this->output->createProgressBar($totalDemos);
        $bar->start();

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        UploadedDemo::chunk($batchSize, function ($demos) use (&$migrated, &$skipped, &$errors, $dryRun, $bar) {
            foreach ($demos as $demo) {
                $bar->advance();

                // Check if file exists and needs migration
                if (!Storage::exists($demo->file_path)) {
                    $this->newLine();
                    $this->error("File not found: {$demo->file_path} (Demo ID: {$demo->id})");
                    $errors++;
                    continue;
                }

                // Check if already in new structure
                if ($this->isInNewStructure($demo->file_path)) {
                    $skipped++;
                    continue;
                }

                // Generate new path
                $newPath = $this->generateNewPath($demo);

                if ($dryRun) {
                    $this->newLine();
                    $this->line("Would move: {$demo->file_path} -> {$newPath}");
                    $migrated++;
                    continue;
                }

                // Perform migration
                try {
                    // Ensure directory exists
                    $directory = dirname($newPath);
                    if (!Storage::exists($directory)) {
                        Storage::makeDirectory($directory);
                    }

                    // Move file
                    Storage::move($demo->file_path, $newPath);

                    // Update database
                    $demo->update(['file_path' => $newPath]);

                    $migrated++;
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Failed to migrate demo {$demo->id}: " . $e->getMessage());
                    $errors++;
                }
            }
        });

        $bar->finish();
        $this->newLine();

        // Summary
        $this->info("Migration complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Migrated', $migrated],
                ['Skipped (already migrated)', $skipped],
                ['Errors', $errors],
                ['Total', $totalDemos]
            ]
        );

        if ($dryRun && $migrated > 0) {
            $this->info("Run without --dry-run to perform the actual migration");
        }

        return $errors > 0 ? 1 : 0;
    }

    /**
     * Check if file is already in new hierarchical structure
     */
    private function isInNewStructure($path)
    {
        // New structure: demos/{type}/{year}/{month}/{day}/{hash}/{filename}
        $parts = explode('/', $path);

        // Should have at least 6 parts for new structure
        if (count($parts) < 6) {
            return false;
        }

        // Check if it follows the pattern
        $typeIndex = array_search('demos', $parts);
        if ($typeIndex === false || !isset($parts[$typeIndex + 4])) {
            return false;
        }

        // Check if year/month/day are numeric
        $year = $parts[$typeIndex + 2] ?? '';
        $month = $parts[$typeIndex + 3] ?? '';
        $day = $parts[$typeIndex + 4] ?? '';

        return is_numeric($year) && is_numeric($month) && is_numeric($day);
    }

    /**
     * Generate new hierarchical path based on demo metadata
     */
    private function generateNewPath($demo)
    {
        $filename = basename($demo->file_path);

        // Use demo creation date for path structure
        $date = $demo->created_at;
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        // Use filename hash for distribution
        $hashPrefix = substr(md5($filename), 0, 2);

        // Determine type based on current path
        $type = str_contains($demo->file_path, '/uploaded/') ? 'uploaded' : 'processed';

        return "demos/{$type}/{$year}/{$month}/{$day}/{$hashPrefix}/{$filename}";
    }
}
