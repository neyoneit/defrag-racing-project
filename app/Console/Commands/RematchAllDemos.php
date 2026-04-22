<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Models\Record;
use App\Models\OfflineRecord;
use App\Services\DemoAutoAssigner;
use Illuminate\Console\Command;

class RematchAllDemos extends Command
{
    protected $signature = 'demos:rematch-all {demo_id? : Optional specific demo ID to rematch}';
    protected $description = 'Rematch all unassigned demos against current user aliases';

    public function handle(DemoAutoAssigner $autoAssigner)
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

        foreach ($demos as $demo) {
            if ($demo->is_offline) {
                // Offline demos (df/fs/fc) never map to online Records.
                // Refresh their fuzzy name-match hint against the current
                // alias set so admin UI / future upgrades stay accurate.
                $nameMatch = $autoAssigner->updateNameMatchOnly($demo);

                if ($demoId) {
                    $this->info("\nDemo ID: {$demo->id} (offline)");
                    $this->info("Player Name: {$demo->player_name}");
                    $this->info("New Suggested User ID: " . ($nameMatch['user_id'] ?? 'null'));
                    $this->info("New Confidence: {$nameMatch['confidence']}%");
                }

                // If the offline demo has a missing offline_record and we
                // have a 100% confident user match, create it. Rank cascade
                // mirrors DemoProcessorService::createOfflineRecord but
                // simplified — rematch has no file-path / validity context.
                if ($nameMatch['confidence'] === 100 && $nameMatch['user_id'] && $demo->status !== 'assigned' && $demo->file_path) {
                    if ($this->ensureOfflineRecord($demo, $demoId !== null)) {
                        $assigned++;
                    }
                }

                // Fix status for offline demos that already have an
                // offline_record but weren't marked assigned.
                if ($demo->status !== 'assigned' && $demo->file_path) {
                    $existing = OfflineRecord::where('demo_id', $demo->id)->first();
                    if ($existing) {
                        $demo->update(['status' => 'assigned']);
                        $assigned++;
                        if ($demoId) {
                            $this->info("✓ Fixed status for offline record ID: {$existing->id}");
                        }
                    }
                }

                $improved++;
                $progressBar->advance();
                continue;
            }

            // Online demo: run the shared PASS 0/1/2 pipeline. The service
            // handles q3df login matching (PASS 0), uploader record
            // matching (PASS 1), fuzzy nick matching (PASS 2), writes
            // match_method, and upgrades any pre-existing offline_record
            // to the online Record (rank cascade + delete).
            $outcome = $autoAssigner->attemptAssignToRecord($demo);

            if ($demoId) {
                $demo->refresh();
                $this->info("\nDemo ID: {$demo->id} (online)");
                $this->info("Player Name: {$demo->player_name}");
                $this->info("Outcome: {$outcome}");
                $this->info("match_method: " . ($demo->match_method ?? 'null'));
                $this->info("record_id: " . ($demo->record_id ?? 'null'));
                $this->info("suggested_user_id: " . ($demo->suggested_user_id ?? 'null'));
                $this->info("name_confidence: {$demo->name_confidence}%");
            }

            if ($outcome === DemoAutoAssigner::OUTCOME_RECORD) {
                $assigned++;
            } elseif ($outcome === DemoAutoAssigner::OUTCOME_PROFILE) {
                // q3df login matched a user but no Record exists. Create
                // an offline_record so the run shows up on that user's
                // leaderboard (parity with DemoProcessorService scénár B).
                if ($demo->file_path) {
                    $demo = $demo->fresh();
                    if ($this->ensureOfflineRecord($demo, $demoId !== null)) {
                        $assigned++;
                    }
                }
            }

            $improved++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Done! Improved confidence for {$improved} demos.");
        $this->info("Assigned {$assigned} demos to records.");
    }

    /**
     * Create an offline_record for a demo if it doesn't have one yet and
     * mark the demo as assigned. Returns true if a new offline_record was
     * created, false if one already existed or required fields were missing.
     *
     * Rank cascade: decrement ranks of existing records slower than this
     * time so the new record slots in at its correct position.
     */
    protected function ensureOfflineRecord(UploadedDemo $demo, bool $verbose): bool
    {
        if (OfflineRecord::where('demo_id', $demo->id)->exists()) {
            return false;
        }
        if (!$demo->file_path || !$demo->map_name || !$demo->physics || !$demo->gametype || !$demo->time_ms) {
            return false;
        }

        $fasterTimes = OfflineRecord::where('map_name', $demo->map_name)
            ->where('physics', $demo->physics)
            ->where('gametype', $demo->gametype)
            ->where('time_ms', '<', $demo->time_ms)
            ->count();

        $offlineRecord = OfflineRecord::create([
            'map_name'    => $demo->map_name,
            'physics'     => $demo->physics,
            'gametype'    => $demo->gametype,
            'time_ms'     => $demo->time_ms,
            'player_name' => $demo->player_name,
            'demo_id'     => $demo->id,
            'rank'        => $fasterTimes + 1,
            'date_set'    => $demo->record_date ?? $demo->created_at,
        ]);

        $demo->update(['status' => 'assigned']);

        OfflineRecord::where('map_name', $demo->map_name)
            ->where('physics', $demo->physics)
            ->where('gametype', $demo->gametype)
            ->where('time_ms', '>=', $demo->time_ms)
            ->where('id', '!=', $offlineRecord->id)
            ->increment('rank');

        if ($verbose) {
            $this->info("✓ Created offline record ID: {$offlineRecord->id}");
        }

        return true;
    }
}
