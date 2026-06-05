<?php

namespace App\Services;

use App\Models\Record;
use App\Models\RecordFlag;
use App\Models\DemosTopRank;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the materialized demos_top_ranks table for a map.
 *
 * For each (base gametype x physics) group on the map it reconstructs the
 * SAME unified field the web map detail shows with "Show Offline" on (main
 * MDD records + Demos Top representatives, ranked by time; oldtop excluded),
 * then writes one row per entry plus a precomputed `auto_render_eligible`
 * flag the auto render queue can filter on directly.
 *
 * Ranking parity with MapsController::buildUnifiedLeaderboard +
 * ::paginateReps is verified empirically against the live controller output
 * (see the rebuild + diff tooling); the clustering itself is the shared,
 * verbatim DemosTopService::buildReps.
 */
class DemosTopRankService
{
    public function __construct(private DemosTopService $demosTop)
    {
    }

    /**
     * Rebuild every group for a map. Returns the number of rows written.
     */
    public function rebuildMap(string $mapName, ?DemoProfileResolver $resolver = null): int
    {
        // One resolver per rebuild (or a shared one injected by the backfill) so
        // the global user/alias buckets load once instead of per group.
        $resolver ??= new DemoProfileResolver();

        $groups = $this->groupsForMap($mapName);

        $written = 0;
        DB::transaction(function () use ($mapName, $groups, $resolver, &$written) {
            DemosTopRank::where('map_name', $mapName)->delete();
            foreach ($groups as $g) {
                $written += $this->rebuildGroup($mapName, $g['gametype'], $g['physics'], $g['pattern'], $resolver);
            }
        });

        return $written;
    }

    /**
     * Enumerate the (gametype, physics, pattern) groups present on a map.
     * Mirrors MapsController's gametype + physics-pattern derivation so the
     * field matches what the map detail builds.
     */
    private function groupsForMap(string $mapName): array
    {
        // Base gametypes that actually have main records (run, ctf1, ...).
        $bases = Record::where('mapname', $mapName)
            ->selectRaw("DISTINCT SUBSTRING_INDEX(gametype, '_', 1) as base")
            ->pluck('base')
            ->filter()
            ->values()
            ->all();

        // Maps with no main records (offline-only) still need a group so their
        // demos get ranked. Default to 'run' / 'ctfN' inferred from the name.
        if (empty($bases)) {
            $bases = str_starts_with($mapName, 'ctf') || str_starts_with($mapName, 'actf')
                ? ['ctf1']
                : ['run'];
        }

        $isCtf = str_starts_with($mapName, 'actf') || str_starts_with($mapName, 'ctf');

        $groups = [];
        foreach ($bases as $base) {
            foreach (['CPM' => 'cpm', 'VQ3' => 'vq3'] as $physics => $suffix) {
                // ctf rounds keep their physics sub-pattern (CPM.1%); plain
                // run maps use the bare CPM%/VQ3% pattern.
                if ($isCtf && str_starts_with($base, 'ctf')) {
                    $ctfNumber = substr($base, 3, 1);
                    $pattern = "{$physics}.{$ctfNumber}%";
                } else {
                    $pattern = "{$physics}%";
                }

                $groups[] = [
                    'gametype' => $base . '_' . $suffix,
                    'physics' => $physics,
                    'pattern' => $pattern,
                ];
            }
        }

        return $groups;
    }

    /**
     * Build + persist one group's ranked field. Returns rows written.
     */
    private function rebuildGroup(string $mapName, string $gametype, string $physics, string $pattern, DemoProfileResolver $resolver): int
    {
        // --- Main records (all, like buildUnifiedLeaderboard) ----------------
        $mainRecords = Record::where('mapname', $mapName)
            ->where('gametype', $gametype)
            ->with(['uploadedDemos'])
            ->get();
        $mainRecordIds = $mainRecords->pluck('id')->toArray();
        $this->attachRecordFlags($mainRecords);

        // --- Demos Top representatives (shared verbatim clustering) ----------
        $dtReps = $this->demosTop->buildReps($mapName, $pattern, $mainRecordIds, $resolver);

        // --- Unify into a single field, same shape paginateReps consumes -----
        $entries = collect();

        foreach ($mainRecords as $record) {
            $entries->push((object) [
                'entry_type' => 'main',
                'record_id' => $record->id,
                'uploaded_demo_id' => $this->renderableDemoIdForRecord($record),
                'time_ms' => (int) $record->time,
                'date_set' => $record->date_set,
                'has_flag' => !empty($record->approved_flags),
                'is_representative' => false, // main records render via online tiers, not via this flag
                'grouped_count' => 0,
            ]);
        }

        foreach ($dtReps as $rep) {
            $entries->push((object) [
                'entry_type' => ($rep->is_online ?? false) ? 'dt_online' : 'dt_offline',
                'record_id' => null,
                // offline rep keys back to uploaded_demos via demo_id;
                // online rep's own id IS the uploaded_demos id.
                'uploaded_demo_id' => $rep->demo_id ?? $rep->id,
                'time_ms' => (int) $rep->time_ms,
                'date_set' => $rep->date_set,
                'has_flag' => $this->repHasFlag($rep),
                'is_representative' => true,
                'grouped_count' => (int) ($rep->grouped_count ?? 0),
            ]);
        }

        if ($entries->isEmpty()) {
            return 0;
        }

        // --- Rank by time, nulling flagged (paginateReps semantics) ----------
        $ranked = $entries->sortBy('time_ms')->values();
        $rank = 0;
        foreach ($ranked as $e) {
            $e->rank = $e->has_flag ? null : ++$rank;
        }
        $groupTotal = $rank; // count of non-null ranks

        // --- Identical-time dedup: only the 3 oldest of any shared time ------
        // are queue-eligible (computed over the ranked field, by date_set).
        $olderSameTime = [];
        foreach ($ranked as $e) {
            $olderSameTime[spl_object_id($e)] = 0;
        }
        $byTime = $ranked->groupBy('time_ms');
        foreach ($byTime as $bucket) {
            $ordered = $bucket->sortBy([
                fn ($a, $b) => strtotime((string) ($a->date_set ?? '')) <=> strtotime((string) ($b->date_set ?? '')),
            ])->values();
            foreach ($ordered as $pos => $e) {
                $olderSameTime[spl_object_id($e)] = $pos; // how many share-time peers are older
            }
        }

        // Field-relative time gate: the WR (rank-1) time is the map's pace, and
        // anything more than `max_wr_ratio` times slower is off the field's
        // level (e.g. a 2-minute run where the map is a 20-second league) and
        // shouldn't be auto-rendered/published no matter its rank. Tunable via
        // SiteSetting; 0/blank disables it.
        $wrTime = optional($ranked->firstWhere('rank', 1))->time_ms;
        $maxRatio = (float) \App\Models\SiteSetting::get('demome:max_wr_ratio', 2.0);

        // --- Persist ---------------------------------------------------------
        $rows = [];
        $now = now();
        foreach ($ranked as $e) {
            $identicalOk = ($olderSameTime[spl_object_id($e)] ?? 0) < DemosTopRank::MAX_IDENTICAL_TIME;
            // Eligible = any RENDERABLE field entry (main record's single demo
            // OR a Demos Top rep - time-history demos have no row at all) whose
            // rank sits in the better half of the field and isn't a redundant
            // identical-time duplicate. is_representative stays purely
            // informational (dt rep vs main).
            // rank 1 (the map's best run in the field) is always worth
            // rendering, even on a tiny field where 1*2 > group_total would
            // otherwise exclude it.
            $withinPace = $maxRatio <= 0
                || $wrTime === null || $wrTime <= 0
                || $e->time_ms <= $wrTime * $maxRatio;

            $eligible = $e->uploaded_demo_id !== null
                && $e->rank !== null
                && ($e->rank === 1 || $e->rank * 2 <= $groupTotal)
                && $identicalOk
                && $withinPace;

            $rows[] = [
                'map_name' => $mapName,
                'group_gametype' => $gametype,
                'physics' => $physics,
                'physics_pattern' => $pattern,
                'entry_type' => $e->entry_type,
                'record_id' => $e->record_id,
                'uploaded_demo_id' => $e->uploaded_demo_id,
                'time_ms' => $e->time_ms,
                'date_set' => $e->date_set,
                'rank' => $e->rank,
                'group_total' => $groupTotal,
                'grouped_count' => $e->grouped_count,
                'is_representative' => $e->is_representative,
                'auto_render_eligible' => $eligible,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DemosTopRank::insert($chunk);
        }

        return count($rows);
    }

    /**
     * Pick the single demo that represents a main record (1-demo-per-record):
     * prefer the demo whose time matches the record, else the oldest upload.
     * Returns null when the record has no attached demo (not renderable).
     */
    private function renderableDemoIdForRecord(Record $record): ?int
    {
        $demos = $record->uploadedDemos;
        if (!$demos || $demos->isEmpty()) {
            return null;
        }

        $exact = $demos->where('time_ms', (int) $record->time)->sortBy('id')->first();
        if ($exact) {
            return (int) $exact->id;
        }

        return (int) $demos->sortBy('id')->first()->id;
    }

    /**
     * Replicate the rep-side flag check from MapsController::paginateReps.
     */
    private function repHasFlag($rep): bool
    {
        if (!empty($rep->approved_flags)) {
            return true;
        }
        return !empty($rep->verification_type)
            && !in_array($rep->verification_type, ['OFFLINE', 'ONLINE', 'verified'], true);
    }

    /**
     * Attach approved community flags to main records (mirrors
     * MapsController::attachCommunityFlags, just the bits rank nulling needs).
     */
    private function attachRecordFlags($records): void
    {
        if (!$records || $records->isEmpty()) {
            return;
        }

        $recordIds = [];
        $demoIds = [];
        foreach ($records as $record) {
            $recordIds[] = $record->id;
            foreach (($record->uploadedDemos ?? []) as $demo) {
                $demoIds[] = $demo->id;
            }
        }

        $flags = RecordFlag::where('status', 'approved')
            ->where(function ($q) use ($recordIds, $demoIds) {
                $q->whereIn('record_id', $recordIds);
                if (!empty($demoIds)) {
                    $q->orWhereIn('demo_id', $demoIds);
                }
            })
            ->get();

        $flagsByRecord = $flags->whereNotNull('record_id')->groupBy('record_id');
        $flagsByDemo = $flags->whereNotNull('demo_id')->groupBy('demo_id');

        foreach ($records as $record) {
            $recordFlags = collect();
            if (isset($flagsByRecord[$record->id])) {
                $recordFlags = $recordFlags->merge($flagsByRecord[$record->id]);
            }
            foreach (($record->uploadedDemos ?? []) as $demo) {
                if (isset($flagsByDemo[$demo->id])) {
                    $recordFlags = $recordFlags->merge($flagsByDemo[$demo->id]);
                }
            }
            $record->approved_flags = $recordFlags
                ->groupBy('flag_type')
                ->map(fn ($g) => $g->sortByDesc('flag_count')->first())
                ->values()
                ->toArray();
        }
    }
}
