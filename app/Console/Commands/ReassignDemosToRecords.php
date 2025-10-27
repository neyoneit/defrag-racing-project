<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UploadedDemo;
use App\Models\Record;
use Illuminate\Support\Facades\DB;

class ReassignDemosToRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demos:reassign
                            {--dry-run : Run without making changes}
                            {--unassigned-only : Only reassign demos with record_id=NULL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reassign uploaded demos to records based on map, physics, time, and user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $unassignedOnly = $this->option('unassigned-only');

        $this->info('Starting demo reassignment...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get demos to process
        $query = UploadedDemo::whereNotNull('map_name')
            ->whereNotNull('physics')
            ->whereNotNull('time_ms')
            ->where('status', '!=', 'failed');

        if ($unassignedOnly) {
            $query->whereNull('record_id');
        }

        $demos = $query->get();
        $this->info("Found {$demos->count()} demos to process");

        $matched = 0;
        $notMatched = 0;
        $multipleMatches = 0;
        $alreadyAssigned = 0;

        $progressBar = $this->output->createProgressBar($demos->count());
        $progressBar->start();

        foreach ($demos as $demo) {
            // Build gametype string (e.g., "run_cpm" or "run_vq3")
            $gametype = 'run_' . strtolower($demo->physics);

            // Try to find matching record
            $recordQuery = Record::where('mapname', $demo->map_name)
                ->where('gametype', $gametype)
                ->where('time', $demo->time_ms);

            // If demo has user_id, match by user
            if ($demo->user_id) {
                $recordQuery->where('user_id', $demo->user_id);
            }

            $matchingRecords = $recordQuery->get();

            if ($matchingRecords->count() === 0) {
                // No match found
                $notMatched++;
                $this->newLine();
                $this->warn("No match: {$demo->original_filename} (map: {$demo->map_name}, gametype: {$gametype}, time: {$demo->time_ms}ms, user_id: {$demo->user_id})");
            } elseif ($matchingRecords->count() > 1) {
                // Multiple matches found - be conservative, don't auto-assign
                $multipleMatches++;
                $this->newLine();
                $this->warn("Multiple matches: {$demo->original_filename} - found {$matchingRecords->count()} records");
            } else {
                // Exact match found
                $record = $matchingRecords->first();

                if ($demo->record_id === $record->id) {
                    $alreadyAssigned++;
                } else {
                    $matched++;
                    $this->newLine();
                    $this->info("✓ Matched: {$demo->original_filename} → Record #{$record->id} ({$record->name} on {$record->mapname})");

                    if (!$dryRun) {
                        $demo->record_id = $record->id;
                        $demo->status = 'assigned';
                        $demo->save();
                    }
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Reassignment Summary ===');
        $this->table(
            ['Status', 'Count'],
            [
                ['Successfully matched', $matched],
                ['Already assigned', $alreadyAssigned],
                ['No match found', $notMatched],
                ['Multiple matches (skipped)', $multipleMatches],
                ['Total processed', $demos->count()],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN: No changes were saved. Run without --dry-run to apply changes.');
        } else {
            $this->info("✓ Reassignment complete! {$matched} demos assigned to records.");
        }

        return Command::SUCCESS;
    }
}
