<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Models\Record;
use App\Models\OfflineRecord;
use App\Services\DemoAutoAssigner;
use App\Services\DemoAutoAssignContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RematchAllDemos extends Command
{
    protected $signature = 'demos:rematch-all
        {demo_id? : Optional specific demo ID to rematch}
        {--chunk=1000 : Batch size for chunkById streaming}
        {--status= : Filter to demos with this exact status (e.g. processed). When set, the rematch only touches that subset instead of every non-assigned demo. Useful after a targeted cleanup unpaired demos to status=processed.}';

    protected $description = 'Rematch all unassigned demos against current user aliases';

    public function handle(DemoAutoAssigner $autoAssigner)
    {
        $demoId = (int) $this->argument('demo_id') ?: null;
        $chunkSize = max(100, (int) $this->option('chunk'));
        $statusFilter = $this->option('status') ?: null;

        // Single-demo mode bypasses chunking/preload entirely — one demo
        // doesn't justify loading ~650k Records into memory, and verbose
        // per-field output only makes sense for a targeted run.
        if ($demoId) {
            $this->info("Rematching demo ID {$demoId}...");
            $demo = UploadedDemo::where('id', $demoId)->whereNotNull('player_name')->first();
            if (!$demo) {
                $this->warn("Demo {$demoId} not found or has no player_name");
                return 0;
            }
            [$improved, $assigned] = $this->processDemo($demo, $autoAssigner, null, true);
            $this->info("Done! Improved: {$improved}, Assigned: {$assigned}");
            return 0;
        }

        if ($statusFilter) {
            $this->info("Rematching demos with status={$statusFilter}...");
            $query = UploadedDemo::where('status', $statusFilter)->whereNotNull('player_name');
        } else {
            $this->info('Rematching all unassigned demos...');
            // Rematch all demos that are NOT assigned (includes: uploaded,
            // processing, processed, failed). player_name is required for
            // fuzzy-match to do anything useful.
            $query = UploadedDemo::where('status', '!=', 'assigned')->whereNotNull('player_name');
        }
        $total = (clone $query)->count();
        $this->info("Found {$total} demos to rematch");

        if ($total === 0) {
            return 0;
        }

        $this->info('Preloading Record index...');
        $t0 = microtime(true);
        $ctx = DemoAutoAssignContext::build();
        $this->info('  -> ' . number_format($ctx->recordCount()) . ' records indexed in ' . round(microtime(true) - $t0, 1) . 's');

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $improved = 0;
        $assigned = 0;

        // chunkById streams the working set without holding all rows in
        // memory at once. Each chunk is wrapped in its own transaction so
        // per-demo UPDATE + rank-cascade statements amortise the commit
        // cost across the batch.
        $query->orderBy('id')->chunkById($chunkSize, function ($demos) use ($autoAssigner, $ctx, $progressBar, &$improved, &$assigned) {
            DB::transaction(function () use ($demos, $autoAssigner, $ctx, $progressBar, &$improved, &$assigned) {
                foreach ($demos as $demo) {
                    [$di, $da] = $this->processDemo($demo, $autoAssigner, $ctx, false);
                    $improved += $di;
                    $assigned += $da;
                    $progressBar->advance();
                }
            });
        });

        $progressBar->finish();
        $this->newLine();
        $this->info("Done! Improved confidence for {$improved} demos.");
        $this->info("Assigned {$assigned} demos to records.");
        return 0;
    }

    /**
     * Run the auto-assign pipeline on a single demo. Returns [improved, assigned]
     * counters so the caller can aggregate across a batch. When $verbose is
     * true, per-demo diagnostics are written to stdout (single-demo mode).
     */
    protected function processDemo(UploadedDemo $demo, DemoAutoAssigner $autoAssigner, ?DemoAutoAssignContext $ctx, bool $verbose): array
    {
        $assigned = 0;

        if ($demo->is_offline) {
            // Offline demos (df/fs/fc) never map to online Records.
            // Refresh their fuzzy name-match hint against the current
            // alias set so admin UI / future upgrades stay accurate.
            $nameMatch = $autoAssigner->updateNameMatchOnly($demo);

            if ($verbose) {
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
                if ($this->ensureOfflineRecord($demo, $verbose)) {
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
                    if ($verbose) {
                        $this->info("✓ Fixed status for offline record ID: {$existing->id}");
                    }
                }
            }

            return [1, $assigned];
        }

        // Online demo: run the shared PASS 0/1/2 pipeline. The service
        // handles q3df login matching (PASS 0), uploader record
        // matching (PASS 1), fuzzy nick matching (PASS 2), writes
        // match_method, and upgrades any pre-existing offline_record
        // to the online Record (rank cascade + delete).
        $outcome = $autoAssigner->attemptAssignToRecord($demo, $ctx);

        if ($verbose) {
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
                if ($this->ensureOfflineRecord($demo, $verbose)) {
                    $assigned++;
                }
            }
        } elseif ($outcome === DemoAutoAssigner::OUTCOME_NONE) {
            // Parity with DemoProcessorService::autoAssignToRecord: when
            // Pass 2 fuzzy nick reached a confident-enough user match but
            // no Record exists at this (map, gametype, time) for that
            // user, create an offline_record so the run is attributed.
            // Without this branch demos that we just unpaired (e.g. via
            // demos:resolve-duplicate-assignments) would never reach the
            // player's profile through rematch — they'd stay as plain
            // 'processed' files invisible on the leaderboard.
            //
            // Threshold lowered from 100 to 80 so Levenshtein-fuzzy matches
            // (e.g. demo player "NOOBZ0RN" → user alias "[NOOB]Z0RN" at 80%)
            // also produce an offline_record. Conservative auto-assignment
            // to a Record still requires 100% in DemoAutoAssigner Pass 2.
            $demo = $demo->fresh();
            if ($demo->file_path && $demo->name_confidence >= 80 && $demo->suggested_user_id) {
                if ($this->ensureOfflineRecord($demo, $verbose)) {
                    $assigned++;
                }
            }
        }

        return [1, $assigned];
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
