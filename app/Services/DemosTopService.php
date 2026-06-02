<?php

namespace App\Services;

use App\Models\OfflineRecord;
use App\Models\UploadedDemo;
use App\Models\RecordFlag;
use Illuminate\Support\Collection;

/**
 * Demos Top representative builder.
 *
 * This is the union-find clustering + MDD-time filter + online/offline
 * subcluster split that turns the raw pool of offline_records and assigned
 * online demos into the Demos Top "representatives" (one fastest online +
 * one fastest offline per virtual player, with time-history folded into
 * grouped_count). It was lifted VERBATIM out of MapsController so the web
 * map detail and any future materialization share one source of truth.
 *
 * Callers still own sort + rank + pagination (see MapsController::paginateReps).
 */
class DemosTopService
{
    /**
     * Build the Demos Top representative collection (no pagination, no rank
     * assignment). Returns a flat collection of candidate objects already
     * run through union-find + MDD-time filter + online/offline subcluster
     * split. Callers layer their own sort + rank + pagination on top.
     */
    public function buildReps(string $mapName, string $physicsPattern, array $mainRecordIds = [], ?DemoProfileResolver $resolver = null): Collection
    {

        // Pool 1: offline_records (with demo eager-loaded so we can read q3df signals)
        $offline = OfflineRecord::where('map_name', $mapName)
            ->where('physics', 'LIKE', $physicsPattern)
            ->with([
                'demo:id,q3df_login_name,q3df_login_name_colored,file_hash,country,user_id,suggested_user_id',
                'demo.suggestedUser',
                'user',
                'renderedVideos' => fn ($q) => $q->visible()->latest(),
            ])
            ->get();

        // Pool 2: assigned online demos not already in main records table
        $onlineDemosQuery = UploadedDemo::where('map_name', $mapName)
            ->where('physics', 'LIKE', $physicsPattern)
            ->where('status', 'assigned')
            ->whereNotNull('record_id')
            ->with(['record.user', 'user', 'renderedVideo']);
        if (!empty($mainRecordIds)) {
            $onlineDemosQuery->whereNotIn('record_id', $mainRecordIds);
        }
        $onlineDemos = $onlineDemosQuery->get();

        // Pool 3: MAIN-attached online demos — included only as cluster members
        // (flagged is_main_attached = true). They never become representatives
        // (main records already appear in the main leaderboard table above).
        // Keeping them in the cluster lets us (a) propagate their registered
        // profile/country to the offline rep for the same virtual player, and
        // (b) embed the verified main-record demo inside the online time-history
        // drawer as context.
        $mainAttachedDemos = collect();
        if (!empty($mainRecordIds)) {
            $mainAttachedDemos = UploadedDemo::where('map_name', $mapName)
                ->where('physics', 'LIKE', $physicsPattern)
                ->where('status', 'assigned')
                ->whereIn('record_id', $mainRecordIds)
                ->with(['record.user', 'user', 'renderedVideo'])
                ->get();
        }

        // MDD record times keyed by profile key ("user:<id>" or "mdd:<id>").
        // Both registered players and unclaimed q3df profiles get included,
        // so the MDD-time filter fires regardless of whether the q3df
        // profile has been claimed on defrag.racing yet.
        $mddTimeByProfileKey = [];
        if (!empty($mainRecordIds)) {
            \App\Models\Record::whereIn('id', $mainRecordIds)
                ->select(['id', 'user_id', 'mdd_id', 'time'])
                ->get()
                ->each(function ($r) use (&$mddTimeByProfileKey) {
                    $t = (int) $r->time;
                    $keys = [];
                    if ($r->user_id) $keys[] = 'user:' . (int) $r->user_id;
                    if ($r->mdd_id)  $keys[] = 'mdd:'  . (int) $r->mdd_id;
                    foreach ($keys as $k) {
                        if (!isset($mddTimeByProfileKey[$k]) || $t < $mddTimeByProfileKey[$k]) {
                            $mddTimeByProfileKey[$k] = $t;
                        }
                    }
                });
        }
        // Priority allow-list for the resolver's ambiguous-plain fallback:
        // if a plain alias is shared between, say, user 41 and user 125 but
        // only user 41 has a main record on this map, attribute ambiguous
        // demos to user 41 (the other namesake has no business being the
        // owner on a map they don't play).
        $priorityProfileKeys = array_keys($mddTimeByProfileKey);

        // Unify into a single candidate list with normalized shape.
        $candidates = collect();

        foreach ($offline as $record) {
            $flagType = 'OFFLINE';
            if ($record->validity_flag) {
                $flagType = $record->validity_flag;
            } elseif ($record->gametype && str_starts_with($record->gametype, 'm')) {
                $flagType = 'ONLINE';
            }

            // Country must come from the demo itself (parsed from filename),
            // never from the demo's uploader. Falling back to the uploader's
            // country means every demo without filename country gets stamped
            // with the admin's / bulk uploader's flag.
            $demoCountry = $record->demo?->country;
            $candidates->push((object) [
                'id' => $record->id,
                'time_ms' => $record->time_ms,
                'time' => $record->time,
                'player_name' => $record->player_name,
                'q3df_login_name' => $record->demo?->q3df_login_name,
                'q3df_login_name_colored' => $record->demo?->q3df_login_name_colored,
                'date_set' => $record->date_set,
                'demo' => $record->demo,
                'demo_id' => $record->demo_id,
                'record_id' => null,
                'user' => null,
                'country' => $demoCountry !== null && $demoCountry !== '' ? $demoCountry : '_404',
                'rank' => $record->rank,
                'is_online' => $record->gametype && str_starts_with($record->gametype, 'm'),
                'is_main_attached' => false,
                'verification_type' => $flagType,
                'rendered_videos' => $record->renderedVideos,
            ]);
        }

        foreach ($onlineDemos as $demo) {
            $user = null;
            $nameToDisplay = $demo->player_name;
            if ($demo->record && $demo->record->user) {
                $user = $demo->record->user;
                $nameToDisplay = $demo->record->user->name;
            } elseif ($demo->record && $demo->record->name) {
                $nameToDisplay = $demo->record->name;
            }

            $candidates->push((object) [
                'id' => $demo->id,
                'time_ms' => $demo->time_ms,
                'time' => $demo->time_ms,
                'player_name' => $demo->player_name,
                'q3df_login_name' => $demo->q3df_login_name,
                'q3df_login_name_colored' => $demo->q3df_login_name_colored,
                'name' => $nameToDisplay,
                'date_set' => $demo->record_date ?? $demo->created_at,
                'demo' => $demo,
                'demo_id' => null,
                'record_id' => $demo->record_id,
                'user' => $user,
                'country' => $demo->record?->country ?? $demo->country,
                'rank' => null,
                'is_online' => true,
                'is_main_attached' => false,
                'verification_type' => 'verified',
                'rendered_videos' => $demo->renderedVideo ? [$demo->renderedVideo] : [],
            ]);
        }

        // Pool 3 candidates: main-attached demos. They carry is_main_attached=true
        // so the rep-selection loop skips them; they still participate in
        // clustering / canonical-user resolution.
        foreach ($mainAttachedDemos as $demo) {
            $user = $demo->record?->user;
            $nameToDisplay = $user?->name ?? $demo->record?->name ?? $demo->player_name;

            $candidates->push((object) [
                'id' => $demo->id,
                'time_ms' => $demo->time_ms,
                'time' => $demo->time_ms,
                'player_name' => $demo->player_name,
                'q3df_login_name' => $demo->q3df_login_name,
                'q3df_login_name_colored' => $demo->q3df_login_name_colored,
                'name' => $nameToDisplay,
                'date_set' => $demo->record_date ?? $demo->created_at,
                'demo' => $demo,
                'demo_id' => null,
                'record_id' => $demo->record_id,
                'user' => $user,
                'country' => $demo->record?->country ?? $demo->country,
                'rank' => null,
                'is_online' => true,
                'is_main_attached' => true,
                'verification_type' => 'verified',
                'rendered_videos' => $demo->renderedVideo ? [$demo->renderedVideo] : [],
            ]);
        }

        if ($candidates->isEmpty()) {
            return collect();
        }

        // Attach community flags to each candidate before clustering so flagged
        // items can be skipped from rank but kept in cluster.
        $allDemoIds = $candidates->pluck('demo_id')->filter()->values()->toArray();
        $embeddedDemoIds = $candidates->map(fn ($c) => $c->demo?->id)->filter()->values()->toArray();
        $allRecordIds = $candidates->pluck('record_id')->filter()->values()->toArray();

        $flagsByDemo = collect();
        $flagsByRecord = collect();
        $mergedDemoIds = array_unique(array_merge($allDemoIds, $embeddedDemoIds));
        if (!empty($mergedDemoIds) || !empty($allRecordIds)) {
            $flags = RecordFlag::where('status', 'approved')
                ->where(function ($q) use ($allRecordIds, $mergedDemoIds) {
                    if (!empty($allRecordIds)) $q->whereIn('record_id', $allRecordIds);
                    if (!empty($mergedDemoIds)) $q->orWhereIn('demo_id', $mergedDemoIds);
                })
                ->get();
            $flagsByDemo = $flags->whereNotNull('demo_id')->groupBy('demo_id');
            $flagsByRecord = $flags->whereNotNull('record_id')->groupBy('record_id');
        }

        $candidates = $candidates->map(function ($item) use ($flagsByDemo, $flagsByRecord) {
            $itemFlags = collect();
            if ($item->demo_id && isset($flagsByDemo[$item->demo_id])) {
                $itemFlags = $itemFlags->merge($flagsByDemo[$item->demo_id]);
            }
            if ($item->demo && isset($flagsByDemo[$item->demo->id])) {
                $itemFlags = $itemFlags->merge($flagsByDemo[$item->demo->id]);
            }
            if ($item->record_id && isset($flagsByRecord[$item->record_id])) {
                $itemFlags = $itemFlags->merge($flagsByRecord[$item->record_id]);
            }
            $item->approved_flags = $itemFlags->groupBy('flag_type')->map(fn ($g) => $g->sortByDesc('flag_count')->first())->values()->toArray();
            return $item;
        });

        // Pre-sort by time so cluster representative is deterministic (fastest).
        $sorted = $candidates->sortBy('time_ms')->values();

        // Union-find over (player_name / q3df_login_name_colored / q3df_login_name).
        $n = $sorted->count();
        $parent = range(0, $n - 1);
        $find = function ($i) use (&$parent) {
            while ($parent[$i] !== $i) { $parent[$i] = $parent[$parent[$i]]; $i = $parent[$i]; }
            return $i;
        };
        $union = function ($a, $b) use (&$parent, $find) {
            $ra = $find($a); $rb = $find($b);
            if ($ra !== $rb) $parent[$ra] = $rb;
        };

        // Flagged candidates (TAS, pmove cheat, no_finish, client_finish=false,
        // etc.) stay as their own singleton clusters — they must not poison a
        // real player's cluster or act as a timehistory seed. E.g. a fake TAS
        // run uploaded under a legitimate player's name would otherwise steal
        // all their attempts into its cluster and show up as the canonical
        // "fastest". Keeping flagged rows standalone lets a reprocess reassign
        // non-flagged demos to the correct owner via name/q3df_login aliases.
        //
        // Two flag sources: community flags (RecordFlag, surfaced as
        // $c->approved_flags) and parser-detected validity flags (stored on
        // OfflineRecord.validity_flag, surfaced here as $c->verification_type
        // with any value outside [OFFLINE, ONLINE, verified]).
        $isFlagged = function ($c) {
            if (!empty($c->approved_flags)) return true;
            return $c->verification_type
                && !in_array($c->verification_type, ['OFFLINE', 'ONLINE', 'verified'], true);
        };

        // Resolver preloads User.plain_name + UserAlias (plain + colored)
        // once and returns the registered user_id for each demo when signals
        // match (colored priority, unique plain fallback). Using this as an
        // extra union key means two demos resolving to the same profile
        // cluster together even when their on-demo player_name / q3df_login
        // values don't match each other — which is the whole point of
        // approving aliases from the admin panel.
        // A shared resolver can be injected (e.g. by the full backfill) so the
        // global user/alias buckets are preloaded once across many maps instead
        // of per group. Buckets are map-independent, so sharing is safe.
        $profileResolver = $resolver ?? new DemoProfileResolver();

        $byName = []; $byColored = []; $byPlain = []; $byUser = [];
        foreach ($sorted as $i => $c) {
            if ($isFlagged($c)) continue;
            $name = strtolower(trim(preg_replace('/\^[0-9\[\]]/', '', $c->player_name ?? '')));
            $colored = trim($c->q3df_login_name_colored ?? '');
            $plain = strtolower(trim($c->q3df_login_name ?? ''));
            if ($name !== '') {
                if (isset($byName[$name])) $union($byName[$name], $i); else $byName[$name] = $i;
            }
            if ($colored !== '') {
                if (isset($byColored[$colored])) $union($byColored[$colored], $i); else $byColored[$colored] = $i;
            }
            if ($plain !== '') {
                if (isset($byPlain[$plain])) $union($byPlain[$plain], $i); else $byPlain[$plain] = $i;
            }

            // Profile-based merge: resolve demo to a registered user or an
            // unclaimed q3df profile via approved aliases. The returned
            // profile key ("user:X" or "mdd:Y") is used as a cluster union
            // key so every demo of the same profile ends up together.
            // Main-attached demos already carry $c->user from their Record
            // relation — use that as the cluster key so they merge with the
            // alias-resolved demos for the same profile (critical for the
            // MDD-time filter to see them as one cluster).
            $resolvedKey = $profileResolver->resolve($c, $priorityProfileKeys);
            if ($resolvedKey === null && !empty($c->user?->id)) {
                $resolvedKey = 'user:' . (int) $c->user->id;
            }
            if ($resolvedKey !== null) {
                if (isset($byUser[$resolvedKey])) $union($byUser[$resolvedKey], $i); else $byUser[$resolvedKey] = $i;
                // Stamp the resolved profile key on the candidate so later
                // passes (canonical user, MDD-time filter) can pick it up.
                if (empty($c->resolved_profile_key)) {
                    $c->resolved_profile_key = $resolvedKey;
                }
            }
        }

        $clustersByRoot = [];
        foreach ($sorted as $i => $c) {
            $r = $find($i);
            $clustersByRoot[$r][] = $i;
        }

        $grouper = new VirtualPlayerGrouper();
        $representatives = [];

        // Each cluster can produce up to two Demos Top rows: one for the
        // fastest non-main-attached online demo and one for the fastest
        // offline demo. The main-attached demo (already shown in the main
        // records table) is never a rep but stays in the cluster so (a) its
        // identity propagates as the canonical profile/country for both reps
        // and (b) it can be embedded in the online history drawer as a
        // "verified" marker.
        foreach ($clustersByRoot as $memberIndices) {
            // Find the canonical profile for this virtual player. Prefer a
            // member with a registered user (typically the main-attached
            // demo's record.user); otherwise fall back to the profile key
            // resolved from aliases. A "user:<id>" key resolves to a real
            // User; "mdd:<id>" resolves to nothing (unclaimed q3df profile
            // — no local user to attach), so offline reps for unclaimed
            // players stay userless but still cluster via mdd_id.
            $canonicalUser = null;
            $canonicalCountry = null;
            $clusterProfileKey = null;
            foreach ($memberIndices as $mi) {
                $m = $sorted[$mi];
                if (!$canonicalUser && !empty($m->user)) {
                    $canonicalUser = $m->user;
                }
                if ($clusterProfileKey === null && !empty($m->resolved_profile_key)) {
                    $clusterProfileKey = $m->resolved_profile_key;
                }
                if (!$canonicalCountry && !empty($m->country) && $m->country !== '_404') {
                    $canonicalCountry = $m->country;
                }
                if ($canonicalUser && $canonicalCountry && $clusterProfileKey) break;
            }
            if (!$canonicalUser && $clusterProfileKey && str_starts_with($clusterProfileKey, 'user:')) {
                $canonicalUser = \App\Models\User::find((int) substr($clusterProfileKey, 5));
            }
            // When we have a registered user, their profile country trumps
            // anything parsed from filenames — keeps both online and offline
            // reps visually consistent (same flag) and matches main-table.
            if ($canonicalUser && !empty($canonicalUser->country) && $canonicalUser->country !== '_404') {
                $canonicalCountry = $canonicalUser->country;
            }

            // Find the fastest MDD record time for this cluster. Two
            // sources because a main record can exist without an attached
            // demo: (a) any main-attached demo already in the cluster, and
            // (b) the preloaded Record table indexed by profile key, which
            // covers both registered users and unclaimed q3df profiles
            // (mdd_id only). This guarantees the MDD-time filter fires
            // even for players who haven't claimed their account yet.
            $mddTime = null;
            foreach ($memberIndices as $mi) {
                $m = $sorted[$mi];
                if (!empty($m->is_main_attached) && $m->time_ms !== null) {
                    if ($mddTime === null || (int) $m->time_ms < $mddTime) {
                        $mddTime = (int) $m->time_ms;
                    }
                }
            }
            if ($mddTime === null && $clusterProfileKey !== null) {
                if (isset($mddTimeByProfileKey[$clusterProfileKey])) {
                    $mddTime = $mddTimeByProfileKey[$clusterProfileKey];
                }
            }
            if ($mddTime === null && $canonicalUser && isset($mddTimeByProfileKey['user:' . $canonicalUser->id])) {
                $mddTime = $mddTimeByProfileKey['user:' . $canonicalUser->id];
            }

            // Partition members into online vs offline subclusters, skipping
            // main-attached demos from rep eligibility but keeping them in
            // the pool (they count toward history later on). Online members
            // at or above the cluster's MDD record time are folded into the
            // main record's implicit history, not surfaced separately.
            $onlineIndices = [];
            $offlineIndices = [];
            foreach ($memberIndices as $mi) {
                $m = $sorted[$mi];
                if (!empty($m->is_main_attached)) continue; // never a rep
                if ($m->is_online) {
                    if ($mddTime !== null && (int) $m->time_ms >= $mddTime) continue;
                    $onlineIndices[] = $mi;
                } else {
                    $offlineIndices[] = $mi;
                }
            }

            $buildRep = function (array $subIndices) use ($sorted, $grouper, $canonicalUser, $canonicalCountry) {
                if (empty($subIndices)) return null;
                // $sorted is asc by time, so first subcluster index = fastest.
                $seedIdx = $subIndices[0];
                $rep = clone $sorted[$seedIdx];

                // Propagate canonical profile so offline rep can still link
                // to a registered user even though its own user is null. When
                // a registered user is known for this cluster, also force
                // their country onto every rep so both rows show the same
                // flag (filename-parsed country otherwise leaks through).
                if ($canonicalUser && empty($rep->user)) {
                    $rep->user = $canonicalUser;
                }
                if ($canonicalUser && !empty($canonicalUser->country) && $canonicalUser->country !== '_404') {
                    $rep->country = $canonicalUser->country;
                } elseif ($canonicalCountry && (empty($rep->country) || $rep->country === '_404')) {
                    $rep->country = $canonicalCountry;
                }

                if (count($subIndices) === 1) {
                    $rep->grouped_count = 0;
                    $rep->grouped_signals = 0;
                    return $rep;
                }

                // Build cluster history siblings (excluding rep itself).
                $rest = [];
                foreach ($subIndices as $mi) {
                    if ($mi !== $seedIdx) $rest[] = $sorted[$mi];
                }
                // Dedupe by file_hash, then by time_ms (keep oldest by date).
                $seenHash = []; $dedup1 = [];
                foreach ($rest as $m) {
                    $hash = $m->demo?->file_hash ?: ('cand:' . $m->id);
                    if (!isset($seenHash[$hash])) { $seenHash[$hash] = true; $dedup1[] = $m; }
                }
                usort($dedup1, fn ($a, $b) => strtotime((string) ($a->date_set ?? '')) - strtotime((string) ($b->date_set ?? '')));
                $seenTime = []; $dedup2 = [];
                foreach ($dedup1 as $m) {
                    $k = 'time:' . (int) $m->time_ms;
                    if (!isset($seenTime[$k])) { $seenTime[$k] = true; $dedup2[] = $m; }
                }
                $rep->grouped_count = count($dedup2);
                $maxSignals = 0;
                foreach ($dedup2 as $m) {
                    $s = $grouper->signalStrength($rep, $m);
                    if ($s > $maxSignals) $maxSignals = $s;
                }
                $rep->grouped_signals = $maxSignals;
                return $rep;
            };

            if ($onlineRep = $buildRep($onlineIndices)) $representatives[] = $onlineRep;
            if ($offlineRep = $buildRep($offlineIndices)) $representatives[] = $offlineRep;
        }

        // Hand back the raw reps — caller owns sort + rank + pagination.
        return collect($representatives);
    }
}
