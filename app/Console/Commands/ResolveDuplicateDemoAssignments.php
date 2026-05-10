<?php

namespace App\Console\Commands;

use App\Models\OfflineRecord;
use App\Models\UploadedDemo;
use App\Models\UserAlias;
use App\Services\NameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs historical mis-assignment of uploaded_demos to Records.
 *
 * Two sub-categories handled:
 *
 * (1) FALSE-POSITIVE PASS 1 — DemoAutoAssigner Pass 1 historically attached
 *     a demo to its uploader's Record on the same (map, gametype, time)
 *     WITHOUT verifying that the demo's player_name actually resolves to
 *     that uploader. Bulk-uploader accounts (admin importing third-party
 *     demos) thus stamped someone else's run as the uploader's own.
 *
 *     Detection: demo.user_id == record.user_id AND
 *                NameMatcher.findBestMatch(player_name).user_id != demo.user_id
 *                (with confidence == 100, i.e. NameMatcher is sure the demo
 *                belongs to a different registered user)
 *     Action: unpair (record_id = NULL, status = 'processed').
 *
 *     Ambiguous sub-case: when multiple registered users share the same
 *     plain alias (e.g. "n", "b", "UnnamedDefragger"), NameMatcher's
 *     first-write-wins rule arbitrarily picks one. We still unpair from
 *     the wrong owner (the demo definitely doesn't belong to neiT just
 *     because they were the uploader) but skip auto-suggestion so the
 *     next rematch doesn't crystalize the arbitrary pick. Demo lands as
 *     a plain 'processed' file for manual review.
 *
 * (2) SUPERSEDED RECORD — demo.record_id points at a Record row whose
 *     `deleted_at` is non-null. The record was soft-deleted because the
 *     same player set a faster time and the slower one was retired. The
 *     demo file is a legitimate older attempt; from the UI's perspective
 *     it currently hangs on a zombie record (frontend hides soft-deleted
 *     records). After unpair + rematch it gets its own offline_record and
 *     surfaces under the player's live record as a Time History sibling.
 *     Action: unpair (record_id = NULL, status = 'processed').
 */
class ResolveDuplicateDemoAssignments extends Command
{
    protected $signature = 'demos:resolve-duplicate-assignments
        {--dry-run : Print what would change without writing}
        {--limit=0 : Cap on the number of items processed (0 = no cap)}';

    protected $description = 'Unpair false-positive Pass 1 assignments and demos on soft-deleted records.';

    public function handle(NameMatcher $matcher)
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
            $this->line('');
        }

        $this->info('=== Detection ===');

        // (1) candidates whose uploader matches the live record owner
        $matched = DB::table('uploaded_demos AS ud')
            ->join('records AS r', function ($j) {
                $j->on('r.id', '=', 'ud.record_id')->whereNull('r.deleted_at');
            })
            ->whereNotNull('ud.record_id')
            ->whereColumn('ud.user_id', 'r.user_id')
            ->select(['ud.id AS demo_id', 'ud.user_id AS uploader', 'ud.player_name', 'ud.record_id', 'ud.map_name', 'ud.time_ms', 'ud.status', 'r.user_id AS owner_user_id', 'r.name AS record_name', 'r.gametype AS record_gametype', 'r.time AS record_time'])
            ->orderBy('ud.id')
            ->get();

        // (2) demos whose record was soft-deleted (= older attempt, superseded)
        $superseded = DB::table('uploaded_demos AS ud')
            ->join('records AS r', 'r.id', '=', 'ud.record_id')
            ->whereNotNull('ud.record_id')
            ->whereNotNull('r.deleted_at')
            ->select(['ud.id AS demo_id', 'ud.user_id AS uploader', 'ud.player_name', 'ud.record_id', 'ud.map_name', 'ud.time_ms', 'ud.status', 'r.deleted_at'])
            ->orderBy('ud.id')
            ->get();

        $this->info("Pass-1 candidates (uploader == record owner, live record): {$matched->count()}");
        $this->info("Superseded demos (record soft-deleted):                    {$superseded->count()}");

        // Classify Pass-1 candidates via NameMatcher and ambiguity check.
        $aliasOwners = $this->buildAliasOwnerCounts();

        $unique = []; $ambiguous = []; $truePositives = 0; $unmatched = 0;
        $progressBar = null;
        if ($matched->count() > 100) {
            $progressBar = $this->output->createProgressBar($matched->count());
            $progressBar->start();
        }
        foreach ($matched as $c) {
            $best = $matcher->findBestMatch($c->player_name, null);
            $bestConf = (int) ($best['confidence'] ?? 0);
            $bestUid  = (int) ($best['user_id'] ?? 0);
            if ($bestConf >= 80 && $bestUid === (int) $c->uploader) {
                $truePositives++;
            } elseif ($bestConf >= 80 && $bestUid !== (int) $c->uploader && $bestUid > 0) {
                $key = $this->normalizeAliasKey($c->player_name);
                $ownerCount = $aliasOwners[$key] ?? 0;
                $row = (object) [
                    'demo_id'      => (int) $c->demo_id,
                    'uploader'     => (int) $c->uploader,
                    'player_name'  => $c->player_name,
                    'record_id'    => (int) $c->record_id,
                    'record_name'  => $c->record_name,
                    'map_name'     => $c->map_name,
                    'record_gametype' => $c->record_gametype,
                    'time_ms'      => (int) $c->time_ms,
                    'status'       => $c->status,
                    'best_user_id' => (int) $best['user_id'],
                    'best_alias'   => $best['matched_name'] ?? null,
                    'owner_count'  => $ownerCount,
                ];
                if ($ownerCount >= 2) {
                    $ambiguous[] = $row;
                } else {
                    $unique[] = $row;
                }
            } else {
                $unmatched++;
            }
            if ($progressBar) $progressBar->advance();
        }
        if ($progressBar) { $progressBar->finish(); $this->newLine(); }

        $this->info("  true-positive   (NameMatcher → uploader, ≥80%):       {$truePositives}  ⇒ ponechat");
        $this->info("  unique unpair   (NameMatcher → JINÝ user, ≥80%, alias má 1 vlastníka): " . count($unique) . "  ⇒ unpair + offline_record pro správného usera");
        $this->info("  ambiguous unpair (alias má ≥2 vlastníky, NameMatcher arbitrární): " . count($ambiguous) . "  ⇒ unpair, NEsuggestovat");
        $this->info("  unmatched       (NameMatcher confidence < 80% / žádný): {$unmatched}  ⇒ ponechat");

        $totalToProcess = count($unique) + count($ambiguous) + $superseded->count();
        if ($totalToProcess === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        if ($limit > 0 && $totalToProcess > $limit) {
            $this->warn("Limiting to first {$limit} item(s) (--limit={$limit}). Order: unique → ambiguous → superseded.");
            $remaining = $limit;
            $unique = array_slice($unique, 0, min($remaining, count($unique)));
            $remaining -= count($unique);
            $ambiguous = array_slice($ambiguous, 0, max(0, min($remaining, count($ambiguous))));
            $remaining -= count($ambiguous);
            $superseded = $superseded->take(max(0, $remaining));
        }

        // ---- Apply ----
        if (!empty($unique)) {
            $this->line('');
            $this->info('--- UNIQUE false-positives (unpair + create offline_record for the correct user) ---');
            foreach ($unique as $u) {
                $bestUser = \App\Models\User::find($u->best_user_id);
                $demo = UploadedDemo::find($u->demo_id);
                $existingLiveRec = \App\Models\Record::where('mapname', $demo->map_name)
                    ->where('gametype', 'run_' . str_replace('.tr','',strtolower($demo->physics ?? '')))
                    ->where('time', $demo->time_ms)
                    ->where('user_id', $u->best_user_id)
                    ->first(['id', 'time']);
                $this->line('');
                $this->line("demo #{$u->demo_id}  {$u->map_name} | {$u->record_gametype} | {$u->time_ms}ms  player='{$u->player_name}'");
                $this->line("  current: rec={$u->record_id} owner='{$u->record_name}' (user_id={$u->uploader})  status={$u->status}");
                $this->line("  NameMatcher: → user_id={$u->best_user_id} (" . ($bestUser?->name ?? '?') . ")  alias='{$u->best_alias}'");
                if ($existingLiveRec) {
                    $this->line("  action: napárovat na live record {$existingLiveRec->id} (správný vlastník)");
                    if (!$dryRun) {
                        DB::transaction(function () use ($u, $existingLiveRec) {
                            UploadedDemo::where('id', $u->demo_id)->update([
                                'record_id'        => $existingLiveRec->id,
                                'status'           => 'assigned',
                                'name_confidence'  => 100,
                                'suggested_user_id'=> $u->best_user_id,
                                'matched_alias'    => $u->best_alias,
                                'match_method'     => 'fp_cleanup_to_live',
                            ]);
                        });
                    }
                } else {
                    $this->line("  action: vytvořit offline_record pod " . ($bestUser?->name ?? '?'));
                    if (!$dryRun) {
                        DB::transaction(function () use ($u, $demo) {
                            // Unpair from neiT and stamp the correct attribution.
                            UploadedDemo::where('id', $u->demo_id)->update([
                                'record_id'        => null,
                                'status'           => 'assigned',
                                'name_confidence'  => 100,
                                'suggested_user_id'=> $u->best_user_id,
                                'matched_alias'    => $u->best_alias,
                                'match_method'     => 'fp_cleanup_offline',
                            ]);
                            $this->createOfflineRecordForDemo($demo, $u->player_name);
                        });
                    }
                }
            }
        }

        if (!empty($ambiguous)) {
            $this->line('');
            $this->info('--- AMBIGUOUS false-positives (unpair only, no auto-suggest) ---');
            foreach ($ambiguous as $a) {
                $bestUser = \App\Models\User::find($a->best_user_id);
                $this->line('');
                $this->line("demo #{$a->demo_id}  {$a->map_name} | {$a->record_gametype} | {$a->time_ms}ms  player='{$a->player_name}'");
                $this->line("  current: rec={$a->record_id} owner='{$a->record_name}' (user_id={$a->uploader})  status={$a->status}");
                $this->line("  NameMatcher arbitrarily picked: user_id={$a->best_user_id} (" . ($bestUser?->name ?? '?') . ")  but alias '{$a->player_name}' is shared by {$a->owner_count} registered users");
                $this->line("  action: record_id=NULL, status=processed, suggested_user_id=NULL (ručně dořešit)");
                if (!$dryRun) {
                    DB::transaction(function () use ($a) {
                        UploadedDemo::where('id', $a->demo_id)->update([
                            'record_id'        => null,
                            'status'           => 'processed',
                            'suggested_user_id'=> null,
                        ]);
                    });
                }
            }
        }

        if ($superseded->isNotEmpty()) {
            $this->line('');
            $this->info('--- SUPERSEDED demos (record soft-deleted) ---');
            foreach ($superseded as $s) {
                $this->line('');
                $this->line("demo #{$s->demo_id}  {$s->map_name} | {$s->time_ms}ms  player='{$s->player_name}'  rec={$s->record_id} (soft-deleted {$s->deleted_at})");
                $this->line('  action: record_id=NULL, status=processed (rematch potom přiřadí přes Pass 2)');
                if (!$dryRun) {
                    DB::transaction(function () use ($s) {
                        UploadedDemo::where('id', $s->demo_id)->update([
                            'record_id' => null,
                            'status'    => 'processed',
                        ]);
                    });
                }
            }
        }

        $this->line('');
        $totalUnpaired = count($unique) + count($ambiguous) + $superseded->count();
        if ($dryRun) {
            $this->info("DRY RUN: would unpair {$totalUnpaired} demos (unique:" . count($unique) . " ambiguous:" . count($ambiguous) . " superseded:{$superseded->count()}).");
        } else {
            $this->info("Done. Unpaired {$totalUnpaired} demos (unique:" . count($unique) . " ambiguous:" . count($ambiguous) . " superseded:{$superseded->count()}).");
            $this->info('Run `php artisan demos:rematch-all` afterwards so Pass 2 can attach these demos to the correct users (their existing records or new offline_records).');
        }

        return 0;
    }

    /**
     * Build a map of alias → number of distinct registered users that own
     * it. Used to detect ambiguous aliases where NameMatcher's
     * first-write-wins rule produces an arbitrary pick.
     */
    protected function buildAliasOwnerCounts(): array
    {
        $rows = UserAlias::where('is_approved', true)
            ->whereNotNull('user_id')
            ->get(['user_id', 'alias', 'alias_colored']);

        $byKey = [];
        foreach ($rows as $row) {
            foreach ([$row->alias, $row->alias_colored] as $name) {
                if (empty($name)) continue;
                $key = $this->normalizeAliasKey($name);
                if ($key === '') continue;
                $byKey[$key][$row->user_id] = true;
            }
        }
        $counts = [];
        foreach ($byKey as $k => $users) {
            $counts[$k] = count($users);
        }
        return $counts;
    }

    /**
     * Mirror of NameMatcher's stripColorCodes + lowercase used for the
     * exact-match alias index, so ambiguity counts line up with what
     * NameMatcher actually keys on.
     */
    protected function normalizeAliasKey(string $name): string
    {
        return strtolower(trim(preg_replace('/\^[0-9\[\]]/', '', $name)));
    }

    /**
     * Create an offline_record for an unpaired false-positive demo so the
     * historical attempt surfaces under the correct player. Mirrors the rank
     * cascade in DemoProcessorService::createOfflineRecord but without the
     * file_path / validity_flag plumbing — these demos already have a stored
     * file from the original (incorrect) processing.
     */
    protected function createOfflineRecordForDemo(UploadedDemo $demo, string $playerName): void
    {
        if (!$demo->map_name || !$demo->physics || !$demo->gametype || !$demo->time_ms) {
            return;
        }
        if (OfflineRecord::where('demo_id', $demo->id)->exists()) {
            return;
        }
        $fasterTimes = OfflineRecord::where('map_name', $demo->map_name)
            ->where('physics', $demo->physics)
            ->where('gametype', $demo->gametype)
            ->whereNull('validity_flag')
            ->where('time_ms', '<', $demo->time_ms)
            ->count();
        $offline = OfflineRecord::create([
            'map_name'    => $demo->map_name,
            'physics'     => $demo->physics,
            'gametype'    => $demo->gametype,
            'time_ms'     => $demo->time_ms,
            'player_name' => $playerName,
            'demo_id'     => $demo->id,
            'rank'        => $fasterTimes + 1,
            'date_set'    => $demo->record_date ?? $demo->created_at,
        ]);
        // Bump rank of any slower offline_records in the same bucket so
        // ranks stay contiguous (mirrors RematchAllDemos::ensureOfflineRecord).
        OfflineRecord::where('map_name', $demo->map_name)
            ->where('physics', $demo->physics)
            ->where('gametype', $demo->gametype)
            ->whereNull('validity_flag')
            ->where('time_ms', '>=', $demo->time_ms)
            ->where('id', '!=', $offline->id)
            ->increment('rank');
    }
}
