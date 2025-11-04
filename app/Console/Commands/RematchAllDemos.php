<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Models\Record;
use App\Services\NameMatcher;
use App\Services\DemoProcessorService;
use Illuminate\Console\Command;

class RematchAllDemos extends Command
{
    protected $signature = 'demos:rematch-all {demo_id? : Optional specific demo ID to rematch}';
    protected $description = 'Rematch all unassigned demos against current user aliases';

    public function handle()
    {
        $demoId = $this->argument('demo_id');

        if ($demoId) {
            $this->info("Rematching demo ID {$demoId}...");
            $demos = UploadedDemo::where('id', $demoId)
                ->whereNotNull('player_name')
                ->get();
        } else {
            $this->info('Rematching all unassigned demos...');
            // Rematch all demos that are NOT assigned (includes: uploaded, processing, processed, failed)
            $demos = UploadedDemo::where('status', '!=', 'assigned')
                ->whereNotNull('player_name')
                ->get();
        }

        $this->info("Found {$demos->count()} demos to rematch");

        $progressBar = $this->output->createProgressBar($demos->count());
        $progressBar->start();

        $improved = 0;
        $assigned = 0;
        $nameMatcher = app(NameMatcher::class);
        $demoProcessor = app(DemoProcessorService::class);

        foreach ($demos as $demo) {
            // PASS 1: For online demos, check if uploader has a matching record (ignores name completely)
            $uploaderRecordMatch = false;
            if (!$demo->is_offline && $demo->user_id && $demo->status !== 'assigned' && $demo->file_path) {
                $physics = str_replace('.tr', '', strtolower($demo->physics));
                $gametype = 'run_' . $physics;

                $uploaderRecord = Record::where('mapname', $demo->map_name)
                    ->where('gametype', $gametype)
                    ->where('time', $demo->time_ms)
                    ->where('user_id', $demo->user_id)
                    ->first();

                if ($uploaderRecord) {
                    // Uploader has a matching record - assign immediately with 100% confidence
                    $demo->update([
                        'record_id' => $uploaderRecord->id,
                        'status' => 'assigned',
                        'name_confidence' => 100,
                        'suggested_user_id' => $demo->user_id,
                        'matched_alias' => null, // Matched by uploader record, not name
                    ]);
                    $assigned++;
                    $uploaderRecordMatch = true;

                    if ($demoId) {
                        $this->info("\n✓ Demo ID: {$demo->id} - Assigned to uploader's record ID: {$uploaderRecord->id} (100% - record match)");
                    }
                }
            }

            // PASS 2: Global name matching (only if not already assigned by Pass 1)
            if (!$uploaderRecordMatch) {
                $nameMatch = $nameMatcher->findBestMatch($demo->player_name, null);

                $oldConfidence = $demo->name_confidence ?? 0;
                $oldUserId = $demo->suggested_user_id;

                if ($demoId) {
                    // Verbose output for single demo
                    $this->info("\nDemo ID: {$demo->id}");
                    $this->info("Player Name: {$demo->player_name}");
                    $this->info("Uploader ID: {$demo->user_id}");
                    $this->info("Old Suggested User ID: " . ($oldUserId ?? 'null'));
                    $this->info("Old Confidence: {$oldConfidence}%");
                    $this->info("New Suggested User ID: " . ($nameMatch['user_id'] ?? 'null'));
                    $this->info("New Confidence: {$nameMatch['confidence']}%");
                    $this->info("Match Source: {$nameMatch['source']}");
                }

                // Always update name confidence, suggested user, and matched alias
                $demo->update([
                    'name_confidence' => $nameMatch['confidence'],
                    'suggested_user_id' => $nameMatch['user_id'],
                    'matched_alias' => $nameMatch['matched_name'] ?? null,
                ]);
            }

            $improved++;

            // Fix offline demo status if it has an offline record but wrong status
            if ($demo->is_offline && $demo->status !== 'assigned' && $demo->file_path) {
                $offlineRecord = \App\Models\OfflineRecord::where('demo_id', $demo->id)->first();

                if ($offlineRecord) {
                    // Offline record exists but demo is not marked as assigned - fix it
                    $demo->update(['status' => 'assigned']);
                    $assigned++;

                    if ($demoId) {
                        $this->info("✓ Fixed status for offline record ID: {$offlineRecord->id}");
                    }
                }
            }

            // If 100% confidence name match, try to auto-assign (only if not already assigned by uploader record match)
            if (!$uploaderRecordMatch && isset($nameMatch) && $nameMatch['confidence'] === 100 && $nameMatch['user_id'] && $demo->status !== 'assigned') {
                // Check if this is an offline demo
                if ($demo->is_offline) {
                    // For offline demos with 100% match, create offline record if missing
                    $offlineRecord = \App\Models\OfflineRecord::where('demo_id', $demo->id)->first();

                    if (!$offlineRecord && $demo->file_path && $demo->map_name && $demo->physics && $demo->gametype && $demo->time_ms) {
                        // Create offline record if it doesn't exist
                        $fasterTimes = \App\Models\OfflineRecord::where('map_name', $demo->map_name)
                            ->where('physics', $demo->physics)
                            ->where('gametype', $demo->gametype)
                            ->where('time_ms', '<', $demo->time_ms)
                            ->count();

                        $rank = $fasterTimes + 1;

                        $offlineRecord = \App\Models\OfflineRecord::create([
                            'map_name' => $demo->map_name,
                            'physics' => $demo->physics,
                            'gametype' => $demo->gametype,
                            'time_ms' => $demo->time_ms,
                            'player_name' => $demo->player_name,
                            'demo_id' => $demo->id,
                            'rank' => $rank,
                            'date_set' => $demo->record_date ?? $demo->created_at,
                        ]);

                        // Mark demo as assigned
                        $demo->update(['status' => 'assigned']);

                        // Update ranks for slower records
                        \App\Models\OfflineRecord::where('map_name', $demo->map_name)
                            ->where('physics', $demo->physics)
                            ->where('gametype', $demo->gametype)
                            ->where('time_ms', '>=', $demo->time_ms)
                            ->where('id', '!=', $offlineRecord->id)
                            ->increment('rank');

                        $assigned++;

                        if ($demoId) {
                            $this->info("✓ Created offline record ID: {$offlineRecord->id}");
                        }
                    }
                } else {
                    // For online demos, try to match to existing records
                    $physics = str_replace('.tr', '', strtolower($demo->physics));
                    $gametype = 'run_' . $physics;

                    // Find matching record
                    $record = Record::where('mapname', $demo->map_name)
                        ->where('gametype', $gametype)
                        ->where('time', $demo->time_ms)
                        ->where('user_id', $nameMatch['user_id'])
                        ->first();

                    if ($record && $demo->file_path) {
                        // Assign demo to record
                        $demo->update([
                            'record_id' => $record->id,
                            'status' => 'assigned',
                        ]);
                        $assigned++;

                        if ($demoId) {
                            $this->info("✓ Assigned to record ID: {$record->id}");
                        }
                    }
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Done! Improved confidence for {$improved} demos.");
        $this->info("Assigned {$assigned} demos to records.");
    }
}
