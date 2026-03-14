<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'records:remove-duplicates {--dry-run : Show what would be deleted without actually deleting} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate records (same map, player, physics, time) keeping only the oldest record';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Finding duplicate records...');

        // Find all duplicate sets (same mapname, name, physics, time)
        $duplicates = DB::select("
            SELECT mapname, name, physics, time, COUNT(*) as count,
                   GROUP_CONCAT(id ORDER BY id) as record_ids
            FROM records
            GROUP BY mapname, name, physics, time
            HAVING count > 1
            ORDER BY count DESC
        ");

        $duplicateCount = count($duplicates);

        if ($duplicateCount === 0) {
            $this->info('No duplicate records found!');
            return 0;
        }

        $totalRecordsToDelete = 0;
        foreach ($duplicates as $duplicate) {
            // Keep oldest (lowest ID), delete the rest
            $totalRecordsToDelete += ($duplicate->count - 1);
        }

        $this->info("Found {$duplicateCount} duplicate sets containing {$totalRecordsToDelete} records to delete.");
        $this->newLine();

        // Show some examples
        $this->info('Examples of duplicates:');
        $exampleCount = min(10, $duplicateCount);
        for ($i = 0; $i < $exampleCount; $i++) {
            $dup = $duplicates[$i];
            $this->line("  - {$dup->mapname} | {$dup->name} | {$dup->physics} | {$dup->time}ms | {$dup->count} duplicates");
        }
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No records will be deleted');
            return 0;
        }

        if (!$force) {
            if (!$this->confirm("Delete {$totalRecordsToDelete} duplicate records (keeping oldest from each set)?", false)) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        $this->info('Deleting duplicate records...');
        $bar = $this->output->createProgressBar($duplicateCount);
        $bar->start();

        $deletedCount = 0;
        foreach ($duplicates as $duplicate) {
            $ids = explode(',', $duplicate->record_ids);
            // Keep first ID (oldest), delete the rest
            $keepId = array_shift($ids);
            $deleteIds = $ids;

            if (!empty($deleteIds)) {
                $deleted = DB::table('records')
                    ->whereIn('id', $deleteIds)
                    ->delete();
                $deletedCount += $deleted;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Deleted {$deletedCount} duplicate records!");
        $this->info("Kept oldest record from each of {$duplicateCount} duplicate sets.");

        return 0;
    }
}
