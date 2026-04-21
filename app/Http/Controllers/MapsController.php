<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Record;
use App\Models\OldtopRecord;
use App\Models\OfflineRecord;
use App\Models\UploadedDemo;
use App\Models\User;
use App\Models\Map;
use App\Models\MddProfile;
use App\Models\RecordFlag;
use App\Models\PlayerMapScore;
use App\Models\MapDifficultyRating;

use App\Filters\MapFilters;
use App\Services\NameMatcher;
use App\Services\VirtualPlayerGrouper;
use App\Services\DemoProfileResolver;

class MapsController extends Controller
{
    public function index(Request $request) {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        // If there are any filter parameters (except page), redirect to filters route
        // But not for partial requests (tag filtering uses partial reload)
        $filterParams = $request->except(['page']);
        if (!empty($filterParams) && !$isPartial) {
            return redirect()->route('maps.filters', $request->all());
        }

        if (!$isPartial) {
            return Inertia::render('Maps')->with('maps', null);
        }

        $maps = Map::query()
            ->select('id', 'name', 'author', 'pk3', 'thumbnail', 'physics', 'gametype', 'weapons', 'items', 'functions', 'is_nsfw', 'date_added', 'created_at')
            ->withAvg('difficultyRatings', 'rating')
            ->withCount('difficultyRatings')
            ->orderBy('date_added', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate(16)
            ->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        return Inertia::render('Maps')->with('maps', $maps);
    }

    public function filters(Request $request) {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        $mapFilters = (new MapFilters())->filter($request);
        $queries = $mapFilters['data'];

        if (!$isPartial) {
            return Inertia::render('Maps')
                ->with('maps', null)
                ->with('queries', $queries);
        }

        $maps = $mapFilters['query'];
        $maps = $maps->paginate(16)->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            $paging = ['page' => $maps->lastPage()];
            return redirect()->route('maps.filters', array_merge($paging, $queries));
        }

        return Inertia::render('Maps')
            ->with('maps', $maps)
            ->with('queries', $queries);
    }

    /**
     * Fake time history for a given demo on a map, reconstructed from all
     * demo uploads matched to the same virtual player via
     * name/q3df_colored/q3df_plain (transitive OR match).
     *
     * Query params:
     *   demo_id : int — UploadedDemo.id to use as seed
     *   physics : 'vq3' | 'cpm'
     */
    public function timeHistory(Request $request, $mapname)
    {
        $demoId = (int) $request->input('demo_id');
        $userId = (int) $request->input('user_id');
        $mddId = (int) $request->input('mdd_id');
        $physics = strtolower((string) $request->input('physics'));

        if ((!$demoId && !$userId && !$mddId) || !in_array($physics, ['vq3', 'cpm'], true)) {
            return response()->json(['error' => 'Invalid parameters'], 422);
        }

        // Profile mode: main record has no attached demo of its own. Use
        // the profile identity (registered user_id or unclaimed mdd_id) as
        // the virtual seed — no real demo to exclude, so every online
        // cluster member shows in the drawer.
        if (!$demoId && ($userId || $mddId)) {
            $profileKey = $userId ? ('user:' . $userId) : ('mdd:' . $mddId);
            return $this->timeHistoryForProfile($mapname, $physics, $profileKey);
        }

        $seed = UploadedDemo::find($demoId);
        if (!$seed) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // Fetch all demos on this map+physics (both online-assigned and offline-only)
        // uploaded_demos.physics is uppercase ("CPM", "VQ3", possibly with ".TR"),
        // so match case-insensitively on the leading segment.
        $physicsUpper = strtoupper($physics);
        $demos = UploadedDemo::where('map_name', $mapname)
            ->where(function ($q) use ($physicsUpper) {
                $q->where('physics', $physicsUpper)
                  ->orWhere('physics', 'LIKE', $physicsUpper . '.%');
            })
            ->with(['renderedVideo', 'user', 'record.user'])
            ->get();

        // Drop flagged demos before clustering. A TAS / no_finish / pmove_cheat
        // demo must not participate in a real player's history — both ways:
        // it shouldn't act as seed (no timehistory button anyway), and it
        // shouldn't appear in other players' drawers just because the name
        // matches. Two flag sources: community flags (RecordFlag) and parser
        // validity flags (OfflineRecord.validity_flag). Seed is checked below.
        $demoIds = $demos->pluck('id')->all();
        $recordIds = $demos->pluck('record_id')->filter()->all();
        $flaggedDemoIds = [];
        if (!empty($demoIds) || !empty($recordIds)) {
            $flaggedDemoIds = RecordFlag::where('status', 'approved')
                ->where(function ($q) use ($demoIds, $recordIds) {
                    if (!empty($demoIds)) $q->whereIn('demo_id', $demoIds);
                    if (!empty($recordIds)) $q->orWhereIn('record_id', $recordIds);
                })
                ->pluck('demo_id')->filter()->unique()->all();
        }
        if (!empty($demoIds)) {
            $validityFlaggedIds = OfflineRecord::whereIn('demo_id', $demoIds)
                ->whereNotNull('validity_flag')
                ->where('validity_flag', '!=', '')
                ->pluck('demo_id')->filter()->unique()->all();
            $flaggedDemoIds = array_values(array_unique(array_merge($flaggedDemoIds, $validityFlaggedIds)));
        }
        if (in_array($seed->id, $flaggedDemoIds, true)) {
            return response()->json(['history' => [], 'signals' => 0]);
        }
        $flaggedSet = array_flip($flaggedDemoIds);
        $demos = $demos->reject(fn ($d) => isset($flaggedSet[$d->id]))->values();

        $grouper = new VirtualPlayerGrouper();
        $cluster = $grouper->classFor($demos, $seed);

        if ($cluster->isEmpty()) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // History filtering:
        //   1. Exclude the seed demo itself — main row already shows it
        //   2. Filter by is_online matching the seed — Demos Top shows
        //      online and offline reps as separate rows, each with its own
        //      time history drawer of the same origin type (so an offline
        //      rep's drawer doesn't pull in online attempts and vice versa)
        // Distinct demo IDs are all shown: two demos with identical time_ms
        // or file_hash but different demo.id are intentionally kept — user
        // wants to see every attempt as long as it's a separate record.
        $seedId = (int) $seed->id;
        $seedIsOnline = $seed->gametype && str_starts_with($seed->gametype, 'm');
        $cluster = $cluster
            ->reject(fn ($d) => (int) $d->id === $seedId)
            ->filter(function ($d) use ($seedIsOnline) {
                $isOnline = $d->gametype && str_starts_with($d->gametype, 'm');
                return $isOnline === $seedIsOnline;
            })
            ->sortBy('time_ms')
            ->values();

        if ($cluster->isEmpty()) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // Signal count against the seed — how confidently did we group this?
        $maxSignals = 0;
        foreach ($cluster as $d) {
            if ((int) $d->id === (int) $seed->id) continue;
            $s = $grouper->signalStrength($seed, $d);
            if ($s > $maxSignals) $maxSignals = $s;
        }

        // The seed demo (the row the user clicked) acts as the canonical
        // representation of the virtual player — all history rows inherit its
        // avatar/country so the leaderboard tells a consistent story.
        //
        // Using the seed (not cluster->first) because we just filtered out the
        // top time from the cluster, but the seed itself is that top time and
        // already has the authoritative identity we want to mirror.
        //
        // IMPORTANT: only promote record.user (registered q3df account), never
        // uploaded_demo.user (the uploader — could be anyone, e.g. admin bulk
        // uploading someone else's demos).
        $canonicalUser = $seed->record?->user;
        $canonicalCountry = $seed->country
            ?? $seed->record?->country
            ?? $canonicalUser?->country
            ?? '_404';
        $canonicalName = $seed->record?->user?->name
            ?? $seed->record?->name
            ?? $seed->player_name;

        // Return MapRecord-compatible shape per entry so the frontend can reuse
        // the same <MapRecord> component (same chips: download, render, YouTube,
        // report, flag — all for free).
        $history = $cluster->map(function ($d) use ($canonicalUser, $canonicalCountry, $canonicalName) {
            // Online-origin demos carry an 'm' prefix on gametype (mdf/mfs/mfc).
            // record_id alone is wrong: a legit mdf demo with no main-record
            // assignment still came from online play and should show ONLINE.
            $isOnline = $d->gametype && str_starts_with($d->gametype, 'm');
            // Demos attached to an MDD record (record_id set) surface inside
            // the online history drawer with a "Verified" chip so the user
            // can tell which attempt corresponds to the official leaderboard.
            $verificationType = $d->record_id
                ? 'verified'
                : ($isOnline ? 'ONLINE' : 'OFFLINE');

            return [
                // Identity
                'id' => $d->id,
                'demo_id' => $d->id,
                'record_id' => $d->record_id,

                // Times & display
                'time' => (int) $d->time_ms,
                'time_ms' => (int) $d->time_ms,
                'date_set' => $d->record_date ?? $d->created_at,
                'player_name' => $d->player_name,
                'name' => $canonicalName,
                'country' => $canonicalCountry,

                // Source / verification
                'is_online' => $isOnline,
                'verification_type' => $verificationType,
                'rank' => null, // no ranking within history view

                // Relations MapRecord needs for chip rendering
                // user inherited from canonical (fastest) demo
                'user' => $canonicalUser,
                'demo' => $d,
                'uploaded_demos' => [],
                'rendered_videos' => $d->renderedVideo ? [$d->renderedVideo] : [],

                // Q3df login extras (for debugging / signal display)
                'q3df_login_name' => $d->q3df_login_name,
                'q3df_login_name_colored' => $d->q3df_login_name_colored,
            ];
        })->values();

        return response()->json([
            'history' => $history,
            'signals' => $maxSignals,
            'seed_demo_id' => (int) $seed->id,
        ]);
    }

    /**
     * Time history for a main record that has no attached demo of its own.
     * Resolves demos via approved aliases against the profile key
     * ("user:<id>" or "mdd:<id>") and returns every online cluster member.
     */
    private function timeHistoryForProfile(string $mapname, string $physics, string $profileKey)
    {
        $user = null;
        if (str_starts_with($profileKey, 'user:')) {
            $user = \App\Models\User::find((int) substr($profileKey, 5));
            if (!$user) {
                return response()->json(['history' => [], 'signals' => 0]);
            }
        } elseif (!str_starts_with($profileKey, 'mdd:')) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        $physicsUpper = strtoupper($physics);
        $demos = UploadedDemo::where('map_name', $mapname)
            ->where(function ($q) use ($physicsUpper) {
                $q->where('physics', $physicsUpper)
                  ->orWhere('physics', 'LIKE', $physicsUpper . '.%');
            })
            ->with(['renderedVideo', 'user', 'record.user'])
            ->get();

        // Drop flagged demos first (same policy as demo-seeded path).
        $demoIds = $demos->pluck('id')->all();
        $recordIds = $demos->pluck('record_id')->filter()->all();
        $flaggedDemoIds = [];
        if (!empty($demoIds) || !empty($recordIds)) {
            $flaggedDemoIds = RecordFlag::where('status', 'approved')
                ->where(function ($q) use ($demoIds, $recordIds) {
                    if (!empty($demoIds)) $q->whereIn('demo_id', $demoIds);
                    if (!empty($recordIds)) $q->orWhereIn('record_id', $recordIds);
                })
                ->pluck('demo_id')->filter()->unique()->all();
        }
        if (!empty($demoIds)) {
            $validityFlaggedIds = OfflineRecord::whereIn('demo_id', $demoIds)
                ->whereNotNull('validity_flag')
                ->where('validity_flag', '!=', '')
                ->pluck('demo_id')->filter()->unique()->all();
            $flaggedDemoIds = array_values(array_unique(array_merge($flaggedDemoIds, $validityFlaggedIds)));
        }
        $flaggedSet = array_flip($flaggedDemoIds);
        $demos = $demos->reject(fn ($d) => isset($flaggedSet[$d->id]))->values();

        // Priority profile keys = profiles that own a main record on this
        // map+physics. Needed so the resolver's ambiguous-plain tiebreaker
        // kicks in and demos whose plain alias is claimed by multiple
        // profiles still attribute to the one with a record on the map.
        $gametype = 'run_' . $physics;
        $priorityProfileKeys = \App\Models\Record::where('mapname', $mapname)
            ->where('gametype', $gametype)
            ->select(['user_id', 'mdd_id'])->get()
            ->flatMap(fn ($r) => array_filter([
                $r->user_id ? 'user:' . (int) $r->user_id : null,
                $r->mdd_id ? 'mdd:' . (int) $r->mdd_id : null,
            ]))->unique()->values()->toArray();

        // Resolve every remaining demo and keep only those that map to
        // this profile AND are online (main record context is always online).
        $resolver = new DemoProfileResolver();
        $matched = $demos->filter(function ($d) use ($resolver, $profileKey, $priorityProfileKeys) {
            $isOnline = $d->gametype && str_starts_with($d->gametype, 'm');
            if (!$isOnline) return false;
            return $resolver->resolve($d, $priorityProfileKeys) === $profileKey;
        })
        ->sortBy('time_ms')
        ->values();

        if ($matched->isEmpty()) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // Canonical identity: registered user wins; otherwise (unclaimed
        // mdd_id profile) fall back to whichever Record's name represents
        // the profile on this map.
        $canonicalUser = $user;
        if (!$canonicalUser) {
            $mddId = (int) substr($profileKey, 4);
            $rec = \App\Models\Record::where('mapname', $mapname)
                ->where('gametype', $gametype)
                ->where('mdd_id', $mddId)
                ->first(['name', 'country']);
            $canonicalCountry = $rec->country ?? '_404';
            $canonicalName = $rec?->name ?? 'Unknown';
        } else {
            $canonicalCountry = $user->country ?? '_404';
            $canonicalName = $user->name;
        }

        $history = $matched->map(function ($d) use ($canonicalUser, $canonicalCountry, $canonicalName) {
            $verificationType = $d->record_id ? 'verified' : 'ONLINE';
            return [
                'id' => $d->id,
                'demo_id' => $d->id,
                'record_id' => $d->record_id,
                'time' => (int) $d->time_ms,
                'time_ms' => (int) $d->time_ms,
                'date_set' => $d->record_date ?? $d->created_at,
                'player_name' => $d->player_name,
                'name' => $canonicalName,
                'country' => $canonicalCountry,
                'is_online' => true,
                'verification_type' => $verificationType,
                'rank' => null,
                'user' => $canonicalUser,
                'demo' => $d,
                'uploaded_demos' => [],
                'rendered_videos' => $d->renderedVideo ? [$d->renderedVideo] : [],
                'q3df_login_name' => $d->q3df_login_name,
                'q3df_login_name_colored' => $d->q3df_login_name_colored,
            ];
        })->values();

        return response()->json([
            'history' => $history,
            'signals' => 0, // signal strength not meaningful for user-keyed cluster
            'seed_demo_id' => null,
        ]);
    }

    public function random(Request $request) {
        $mapFilters = (new MapFilters())->filter($request);
        $maps = $mapFilters['query'];

        $map = $maps->reorder()->inRandomOrder()->first();

        if (!$map || !$map->name) {
            return response()->json(['error' => 'No maps match the current filters'], 404);
        }

        return response()->json(['name' => $map->name]);
    }

    /**
     * API endpoint: return MDD profiles for filter dropdowns (lazy-loaded)
     */
    public function profiles()
    {
        $profiles = MddProfile::orderBy('id', 'DESC')
            ->with('user:id,name,plain_name,country')
            ->get(['id', 'user_id', 'name', 'country', 'plain_name']);

        return response()->json($profiles);
    }

    public function map(Request $request, $mapname) {
        $column = $request->input('sort', 'time');
        $order = $request->input('order', 'ASC');

        $map = Map::where('name', $mapname)->with(['tags.parent:id,name,display_name', 'tags.children:id,name,display_name,parent_tag_id'])->firstOrFail();

        // Auto-detect most populated gametype if not specified
        $gametype = $request->input('gametype');
        if (!$gametype) {
            // Get record counts per gametype
            $gametypeCounts = Record::where('mapname', $map->name)
                ->selectRaw('SUBSTRING_INDEX(gametype, "_", 1) as base_gametype, COUNT(*) as count')
                ->groupBy('base_gametype')
                ->orderBy('count', 'DESC')
                ->first();

            $gametype = $gametypeCounts ? $gametypeCounts->base_gametype : 'run';
        }

        $cpmGametype = $gametype . '_cpm';
        $vq3Gametype = $gametype . '_vq3';


        if ($request->user() && $request->user()->mdd_id) {
            $my_cpm_record = Record::where('mapname', $mapname)->where('mdd_id', $request->user()->mdd_id)->where('gametype', $cpmGametype)->with('user')->first();
            $my_vq3_record = Record::where('mapname', $mapname)->where('mdd_id', $request->user()->mdd_id)->where('gametype', $vq3Gametype)->with('user')->first();
        } else {
            $my_cpm_record = null;
            $my_vq3_record = null;
        }

        if (! in_array($column, ['date_set', 'time'])) {
            $column = 'date_set';
        }

        if (! in_array($order, ['DESC', 'ASC'])) {
            $order = 'DESC';
        }

        // Get record counts per gametype for UI display
        $gametypeStats = Record::where('mapname', $map->name)
            ->selectRaw('SUBSTRING_INDEX(gametype, "_", 1) as base_gametype, COUNT(*) as total')
            ->groupBy('base_gametype')
            ->get()
            ->keyBy('base_gametype')
            ->map(fn($item) => $item->total)
            ->toArray();

        $cpmRecords = Record::where('mapname', $map->name);

        $cpmRecords = $cpmRecords->where('gametype', $cpmGametype);

        // Get all CPM records sorted by time to calculate proper time-based ranks
        $allCpmRecordsByTime = Record::where('mapname', $map->name)
            ->where('gametype', $cpmGametype)
            ->orderBy('time', 'ASC')
            ->orderBy('date_set', 'ASC')
            ->pluck('id')
            ->toArray();

        $cpmRecords = $cpmRecords->with(['user', 'uploadedDemos', 'renderedVideos' => fn($q) => $q->visible()->latest()])->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'cpmPage')->withQueryString();

        // Attach community flags before ranking
        $this->attachCommunityFlags($cpmRecords);

        // Get IDs of records with approved community flags (excluded from ranking)
        $cpmFlaggedIds = $cpmRecords->getCollection()->filter(fn ($r) => !empty($r->approved_flags))->pluck('id')->toArray();
        $allCpmFlaggedIds = RecordFlag::where('status', 'approved')->whereNotNull('record_id')
            ->whereIn('record_id', $allCpmRecordsByTime)->pluck('record_id')->unique()->toArray();
        // Also check flags via demos
        $cpmDemoFlaggedRecordIds = [];
        $cpmRecordDemoIds = UploadedDemo::whereIn('record_id', $allCpmRecordsByTime)->whereNotNull('record_id')->pluck('id', 'record_id')->toArray();
        if (!empty($cpmRecordDemoIds)) {
            $flaggedDemoIds = RecordFlag::where('status', 'approved')->whereIn('demo_id', array_values($cpmRecordDemoIds))->pluck('demo_id')->unique()->toArray();
            $cpmDemoFlaggedRecordIds = collect($cpmRecordDemoIds)->filter(fn ($demoId) => in_array($demoId, $flaggedDemoIds))->keys()->toArray();
        }
        $allCpmFlaggedIds = array_unique(array_merge($allCpmFlaggedIds, $cpmDemoFlaggedRecordIds));

        // Calculate ranks excluding flagged records
        $cpmRankMap = [];
        $rank = 0;
        foreach ($allCpmRecordsByTime as $id) {
            if (in_array($id, $allCpmFlaggedIds)) {
                $cpmRankMap[$id] = null;
            } else {
                $rank++;
                $cpmRankMap[$id] = $rank;
            }
        }
        $cpmRecords->getCollection()->transform(function ($record) use ($cpmRankMap) {
            $record->rank = $cpmRankMap[$record->id] ?? null;
            return $record;
        });

        $vq3Records = Record::where('mapname', $map->name);

        $vq3Records = $vq3Records->where('gametype', $vq3Gametype);

        // Get all VQ3 records sorted by time to calculate proper time-based ranks
        $allVq3RecordsByTime = Record::where('mapname', $map->name)
            ->where('gametype', $vq3Gametype)
            ->orderBy('time', 'ASC')
            ->orderBy('date_set', 'ASC')
            ->pluck('id')
            ->toArray();

        $vq3Records = $vq3Records->with(['user', 'uploadedDemos', 'renderedVideos' => fn($q) => $q->visible()->latest()])->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'vq3Page')->withQueryString();

        // Attach community flags before ranking
        $this->attachCommunityFlags($vq3Records);

        $allVq3FlaggedIds = RecordFlag::where('status', 'approved')->whereNotNull('record_id')
            ->whereIn('record_id', $allVq3RecordsByTime)->pluck('record_id')->unique()->toArray();
        $vq3RecordDemoIds = UploadedDemo::whereIn('record_id', $allVq3RecordsByTime)->whereNotNull('record_id')->pluck('id', 'record_id')->toArray();
        if (!empty($vq3RecordDemoIds)) {
            $flaggedDemoIds = RecordFlag::where('status', 'approved')->whereIn('demo_id', array_values($vq3RecordDemoIds))->pluck('demo_id')->unique()->toArray();
            $vq3DemoFlaggedRecordIds = collect($vq3RecordDemoIds)->filter(fn ($demoId) => in_array($demoId, $flaggedDemoIds))->keys()->toArray();
            $allVq3FlaggedIds = array_unique(array_merge($allVq3FlaggedIds, $vq3DemoFlaggedRecordIds));
        }

        $vq3RankMap = [];
        $rank = 0;
        foreach ($allVq3RecordsByTime as $id) {
            if (in_array($id, $allVq3FlaggedIds)) {
                $vq3RankMap[$id] = null;
            } else {
                $rank++;
                $vq3RankMap[$id] = $rank;
            }
        }
        $vq3Records->getCollection()->transform(function ($record) use ($vq3RankMap) {
            $record->rank = $vq3RankMap[$record->id] ?? null;
            return $record;
        });

        // Attach map scores (map_score, reltime) from player_map_scores
        $this->attachMapScores($cpmRecords, $map->name, 'cpm', $gametype);
        $this->attachMapScores($vq3Records, $map->name, 'vq3', $gametype);

        $userDefaultOldtop = $request->user()?->default_show_oldtop ? 'true' : 'false';
        $showOldtop = $request->has('showOldtop') ? $request->input('showOldtop') : $userDefaultOldtop;

        if ($showOldtop === 'true') {
             // old top
            $cpmOldRecords = OldtopRecord::where('mapname', $map->name);

            $cpmOldRecords = $cpmOldRecords->where('gametype', $cpmGametype);

            $cpmOldRecords = $cpmOldRecords->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'cpmPage')->withQueryString();

            $vq3OldRecords = OldtopRecord::where('mapname', $map->name);

            $vq3OldRecords = $vq3OldRecords->where('gametype', $vq3Gametype);

            $vq3OldRecords = $vq3OldRecords->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'vq3Page')->withQueryString();
            // oldtop end
        } else {
            $cpmOldRecords = null;
            $vq3OldRecords = null;
        }

        // Offline demos
        $userDefaultOffline = $request->user()?->default_show_offline ? 'true' : 'false';
        $showOffline = $request->has('showOffline') ? $request->input('showOffline') : $userDefaultOffline;

        if ($showOffline === 'true') {
            // Determine physics patterns based on map name + selected ctf mode.
            // fc (fast caps) maps start with "actf" or "ctf", otherwise df (defrag).
            // Physics format in DB: "CPM.2.TR" or "VQ3.1" (N = ctf mode).
            $offlineGametype = (str_starts_with($map->name, 'actf') || str_starts_with($map->name, 'ctf')) ? 'fc' : 'df';
            if ($offlineGametype === 'fc' && strpos($gametype, 'ctf') === 0) {
                $ctfNumber = substr($gametype, 3, 1);
                $cpmPattern = "CPM.{$ctfNumber}%";
                $vq3Pattern = "VQ3.{$ctfNumber}%";
            } else {
                $cpmPattern = 'CPM%';
                $vq3Pattern = 'VQ3%';
            }

            $cpmOfflineRecords = $this->buildGroupedDemosTop($map->name, $cpmPattern, $column, $order, 'cpmPage', $allCpmRecordsByTime);
            $vq3OfflineRecords = $this->buildGroupedDemosTop($map->name, $vq3Pattern, $column, $order, 'vq3Page', $allVq3RecordsByTime);
        } else {
            $cpmOfflineRecords = null;
            $vq3OfflineRecords = null;
        }

        $cpmPage = ($request->has('cpmPage')) ? min($request->cpmPage, $cpmRecords->lastPage()) : 1;

        $vq3Page = ($request->has('vq3Page')) ? min($request->vq3Page, $vq3Records->lastPage()) : 1;

        if ($request->has('vq3Page') && $request->get('vq3Page') > $vq3Records->lastPage()) {
            return redirect()->route('maps.map', ['vq3Page' => $vq3Records->lastPage(), 'mapname' => $mapname, 'cpmPage' => $cpmPage]);
        }

        if ($request->has('cpmPage') && $request->get('cpmPage') > $cpmRecords->lastPage()) {
            return redirect()->route('maps.map', ['cpmPage' => $cpmRecords->lastPage(), 'mapname' => $mapname, 'vq3Page' => $vq3Page]);
        }

        // Precompute time-history cluster metadata per demo for this map.
        // Frontend shows these counts/signal strengths on the leaderboard
        // *without* having to open each drawer — mirrors what the time-history
        // endpoint computes so the numbers match before and after click.
        // Priority profile keys ("user:X" / "mdd:Y") that own a main record
        // on this map+physics. The cluster metadata uses them as a
        // tiebreaker for ambiguous plain aliases — same heuristic as
        // buildGroupedDemosTop, so the badge counts stay consistent with
        // the Demos Top rep counts.
        $cpmMainKeys = \App\Models\Record::whereIn('id', $allCpmRecordsByTime)
            ->select(['user_id', 'mdd_id'])->get()
            ->flatMap(fn ($r) => array_filter([
                $r->user_id ? 'user:' . (int) $r->user_id : null,
                $r->mdd_id ? 'mdd:' . (int) $r->mdd_id : null,
            ]))->unique()->values()->toArray();
        $vq3MainKeys = \App\Models\Record::whereIn('id', $allVq3RecordsByTime)
            ->select(['user_id', 'mdd_id'])->get()
            ->flatMap(fn ($r) => array_filter([
                $r->user_id ? 'user:' . (int) $r->user_id : null,
                $r->mdd_id ? 'mdd:' . (int) $r->mdd_id : null,
            ]))->unique()->values()->toArray();
        $clusterMetaVq3 = $this->computeClusterMetadataForMap($map->name, 'vq3', $vq3MainKeys);
        $clusterMetaCpm = $this->computeClusterMetadataForMap($map->name, 'cpm', $cpmMainKeys);

        // Get servers currently playing this map
        $servers = \App\Models\Server::where('map', $map->name)
            ->where('online', true)
            ->where('visible', true)
            ->with('onlinePlayers')
            ->get();

        // Get public maplists that include this map
        $publicMaplists = \App\Models\Maplist::whereHas('maps', function($query) use ($map) {
                $query->where('map_id', $map->id);
            })
            ->where('is_public', true)
            ->where('is_play_later', false)
            ->withCount('maps')
            ->with('user:id,name')
            ->orderBy('favorites_count', 'desc')
            ->limit(10)
            ->get();

        // Difficulty rating
        $difficultyRatings = MapDifficultyRating::where('map_id', $map->id)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();
        $difficultyTotal = array_sum($difficultyRatings);
        $difficultyAvg = $difficultyTotal > 0
            ? round(array_sum(array_map(fn($r, $c) => $r * $c, array_keys($difficultyRatings), array_values($difficultyRatings))) / $difficultyTotal, 1)
            : null;
        $userDifficultyRating = $request->user()
            ? MapDifficultyRating::where('map_id', $map->id)->where('user_id', $request->user()->id)->value('rating')
            : null;

        return Inertia::render('MapView')
            ->with('map', $map)
            ->with('cpmRecords', $cpmRecords)
            ->with('vq3Records', $vq3Records)
            ->with('my_cpm_record', $my_cpm_record)
            ->with('my_vq3_record', $my_vq3_record)
            ->with('cpmOldRecords', $cpmOldRecords)
            ->with('vq3OldRecords', $vq3OldRecords)
            ->with('cpmOfflineRecords', $cpmOfflineRecords)
            ->with('vq3OfflineRecords', $vq3OfflineRecords)
            ->with('gametypeStats', $gametypeStats)
            ->with('showOldtop', ($showOldtop === 'true'))
            ->with('showOffline', ($showOffline === 'true'))
            ->with('servers', $servers)
            ->with('publicMaplists', $publicMaplists)
            ->with('clusterMetaVq3', $clusterMetaVq3)
            ->with('clusterMetaCpm', $clusterMetaCpm)
            ->with('difficultyRating', [
                'average' => $difficultyAvg,
                'total' => $difficultyTotal,
                'distribution' => $difficultyRatings,
                'user_rating' => $userDifficultyRating,
            ]);

    }

    /**
     * For every uploaded_demo on a map (given physics), return how many demos
     * end up in its virtual-player cluster after applying the same dedupe
     * rules as the time-history endpoint (exclude seed, dedupe by file_hash
     * and by time_ms keeping the oldest). Also returns the max signal
     * strength (0-3) between the seed and any other cluster member.
     *
     * Returns a map: ['<demo_id>' => ['count' => int, 'signals' => int]]
     * so the frontend can look up per-row badge data in O(1).
     */
    private function computeClusterMetadataForMap(string $mapname, string $physics, array $priorityProfileKeys = []): array
    {
        $physicsUpper = strtoupper($physics);
        $demos = UploadedDemo::where('map_name', $mapname)
            ->where(function ($q) use ($physicsUpper) {
                $q->where('physics', $physicsUpper)
                  ->orWhere('physics', 'LIKE', $physicsUpper . '.%');
            })
            ->get(['id', 'player_name', 'q3df_login_name', 'q3df_login_name_colored',
                   'time_ms', 'gametype', 'record_date', 'record_id', 'file_hash', 'created_at']);

        if ($demos->isEmpty()) {
            return [];
        }

        // Identify flagged demos — they must not cluster with legitimate attempts.
        // Two sources to merge: community flags (RecordFlag) and parser-detected
        // validity flags (OfflineRecord.validity_flag, keyed by demo_id).
        $demoIds = $demos->pluck('id')->all();
        $recordIds = $demos->pluck('record_id')->filter()->all();
        $flaggedDemoIds = [];
        if (!empty($demoIds) || !empty($recordIds)) {
            $flaggedDemoIds = RecordFlag::where('status', 'approved')
                ->where(function ($q) use ($demoIds, $recordIds) {
                    if (!empty($demoIds)) $q->whereIn('demo_id', $demoIds);
                    if (!empty($recordIds)) $q->orWhereIn('record_id', $recordIds);
                })
                ->pluck('demo_id')->filter()->unique()->all();
        }
        if (!empty($demoIds)) {
            $validityFlaggedIds = OfflineRecord::whereIn('demo_id', $demoIds)
                ->whereNotNull('validity_flag')
                ->where('validity_flag', '!=', '')
                ->pluck('demo_id')->filter()->unique()->all();
            $flaggedDemoIds = array_values(array_unique(array_merge($flaggedDemoIds, $validityFlaggedIds)));
        }
        $flaggedSet = array_flip($flaggedDemoIds);

        $grouper = new VirtualPlayerGrouper();

        // Union-find: compute root per demo once, reuse for all lookups.
        $n = $demos->count();
        $parent = range(0, $n - 1);
        $find = function ($i) use (&$parent) {
            while ($parent[$i] !== $i) { $parent[$i] = $parent[$parent[$i]]; $i = $parent[$i]; }
            return $i;
        };
        $union = function ($a, $b) use (&$parent, $find) {
            $ra = $find($a); $rb = $find($b);
            if ($ra !== $rb) $parent[$ra] = $rb;
        };

        // Same profile resolver as buildGroupedDemosTop so the cluster
        // metadata (which drives the TimeHistory badge counts in the main
        // records table) agrees with the Demos Top rep counts.
        $profileResolver = new DemoProfileResolver();

        $byName = []; $byColored = []; $byPlain = []; $byUser = [];
        $demosArr = $demos->values();
        foreach ($demosArr as $i => $d) {
            if (isset($flaggedSet[$d->id])) continue;
            $name = strtolower(trim(preg_replace('/\^[0-9\[\]]/', '', $d->player_name ?? '')));
            $colored = trim($d->q3df_login_name_colored ?? '');
            $plain = strtolower(trim($d->q3df_login_name ?? ''));
            if ($name !== '') {
                if (isset($byName[$name])) $union($byName[$name], $i); else $byName[$name] = $i;
            }
            if ($colored !== '') {
                if (isset($byColored[$colored])) $union($byColored[$colored], $i); else $byColored[$colored] = $i;
            }
            if ($plain !== '') {
                if (isset($byPlain[$plain])) $union($byPlain[$plain], $i); else $byPlain[$plain] = $i;
            }
            $resolvedKey = $profileResolver->resolve($d, $priorityProfileKeys);
            if ($resolvedKey !== null) {
                if (isset($byUser[$resolvedKey])) $union($byUser[$resolvedKey], $i); else $byUser[$resolvedKey] = $i;
            }
        }

        // Group items by root.
        $clusters = [];
        foreach ($demosArr as $i => $d) {
            $r = $find($i);
            $clusters[$r][] = $d;
        }

        // Count helper — same policy as /time-history: exclude the seed
        // (its own demo id) and count the rest. Distinct demo_ids with the
        // same file_hash or time_ms are intentionally kept; the drawer
        // shows every attempt that's a separate upload.
        $dedupeAndCount = function (array $subMembers, object $seed) use ($grouper) {
            if (empty($subMembers)) return [0, 0];
            $rest = array_values(array_filter($subMembers, fn ($d) => (int) $d->id !== (int) $seed->id));
            $maxSignals = 0;
            foreach ($rest as $d) {
                $s = $grouper->signalStrength($seed, $d);
                if ($s > $maxSignals) $maxSignals = $s;
            }
            return [count($rest), $maxSignals];
        };

        // Count without excluding any seed — used for user-keyed main record
        // cluster meta where the record has no demo of its own to exclude.
        $dedupeAndCountAll = function (array $subMembers) {
            return count($subMembers);
        };

        $meta = [];
        foreach ($clusters as $members) {
            if (count($members) === 1) {
                $d = $members[0];
                $meta[(string) $d->id] = ['count' => 0, 'signals' => 0];
                continue;
            }

            // Split by is_online (gametype starts with 'm' = online origin).
            // Every demo's badge should only count members of its own type —
            // otherwise the count on a main record's row wouldn't match the
            // contents of its (online-filtered) time-history drawer.
            $onlineMembers = []; $offlineMembers = [];
            foreach ($members as $d) {
                if ($d->gametype && str_starts_with($d->gametype, 'm')) $onlineMembers[] = $d;
                else $offlineMembers[] = $d;
            }
            // Sort each subcluster by time ASC so seed = fastest of that type.
            usort($onlineMembers, fn ($a, $b) => (int) $a->time_ms - (int) $b->time_ms);
            usort($offlineMembers, fn ($a, $b) => (int) $a->time_ms - (int) $b->time_ms);

            [$onlineCount, $onlineSignals] = $onlineMembers
                ? $dedupeAndCount($onlineMembers, $onlineMembers[0])
                : [0, 0];
            [$offlineCount, $offlineSignals] = $offlineMembers
                ? $dedupeAndCount($offlineMembers, $offlineMembers[0])
                : [0, 0];

            foreach ($members as $m) {
                $isOnline = $m->gametype && str_starts_with($m->gametype, 'm');
                $meta[(string) $m->id] = $isOnline
                    ? ['count' => $onlineCount, 'signals' => $onlineSignals]
                    : ['count' => $offlineCount, 'signals' => $offlineSignals];
            }

            // Emit profile-key entry so main table records without any
            // attached demo still surface a TimeHistory badge. Key can be
            // "user:<id>" (registered defrag.racing account) or "mdd:<id>"
            // (unclaimed q3df profile). Frontend falls back to
            // record.user_id → meta["user:X"] or record.mdd_id →
            // meta["mdd:Y"] when the row has no attached demo.
            $clusterProfileKey = null;
            foreach ($members as $m) {
                $rk = $profileResolver->resolve($m, $priorityProfileKeys);
                if ($rk !== null) { $clusterProfileKey = $rk; break; }
            }
            if ($clusterProfileKey !== null && !empty($onlineMembers)) {
                $meta[$clusterProfileKey] = [
                    'count' => $dedupeAndCountAll($onlineMembers),
                    'signals' => $onlineSignals,
                    'profileKey' => $clusterProfileKey,
                ];
            }
        }

        return $meta;
    }

    /**
     * Build "Demos Top" paginator with server-side virtual-player grouping.
     * Pools offline_records + assigned online demos for the map/physics, runs
     * union-find on (player_name / q3df_login_name_colored / q3df_login_name),
     * keeps the fastest attempt per cluster as the representative, then
     * paginates the representatives. Guarantees one row per virtual player and
     * stable cross-page behavior.
     */
    private function buildGroupedDemosTop(string $mapName, string $physicsPattern, string $column, string $order, string $pageName, array $mainRecordIds = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $perPage = 50;
        $currentPage = (int) (request()->input($pageName, 1));
        if ($currentPage < 1) $currentPage = 1;

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
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $currentPage, [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => $pageName,
            ]);
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
        $profileResolver = new DemoProfileResolver();

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

        $reps = collect($representatives);

        // Sort representatives (column = 'time' or 'date_set').
        if ($column === 'date_set') {
            $reps = $order === 'ASC'
                ? $reps->sortBy(fn ($r) => strtotime((string) ($r->date_set ?? '')))->values()
                : $reps->sortByDesc(fn ($r) => strtotime((string) ($r->date_set ?? '')))->values();
        } else {
            $reps = $reps->sortBy('time_ms')->values();
        }

        // Recalculate ranks: skip flagged reps.
        $rank = 0;
        $reps = $reps->map(function ($item) use (&$rank) {
            $hasFlag = !empty($item->approved_flags) ||
                ($item->verification_type && !in_array($item->verification_type, ['OFFLINE', 'ONLINE', 'verified']));
            $item->rank = $hasFlag ? null : ++$rank;
            return $item;
        });

        // Manual pagination over representatives.
        $total = $reps->count();
        $offset = ($currentPage - 1) * $perPage;
        $items = $reps->slice($offset, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Get unassigned demos that potentially match online records for a map.
     * Returns record_id => [matching demos] for matches above 30% confidence.
     */
    public function getDemoMatches(Request $request, $mapname)
    {
        $nameMatcher = app(NameMatcher::class);

        // Get all unassigned demos for this map
        $demos = UploadedDemo::where('map_name', $mapname)
            ->whereNull('record_id')
            ->whereIn('status', ['processed', 'fallback-assigned'])
            ->whereNotNull('player_name')
            ->get(['id', 'player_name', 'time_ms', 'physics', 'gametype', 'original_filename', 'created_at']);

        if ($demos->isEmpty()) {
            return response()->json([]);
        }

        // Get all online records for this map with user info
        $records = Record::where('mapname', $mapname)
            ->with('user:id,name,plain_name,mdd_id')
            ->get(['id', 'name', 'mdd_id', 'gametype', 'time']);

        if ($records->isEmpty()) {
            return response()->json([]);
        }

        $matches = [];

        // Helper to extract physics base (CPM/VQ3) and CTF number
        $parsePhysics = function ($physics, $gametype) {
            $base = 'VQ3';
            $ctfNum = null;

            // Record gametype: "ctf1_vq3", "ctf2_cpm", "run_vq3", etc.
            if ($gametype && preg_match('/ctf(\d+)_(cpm|vq3)/i', $gametype, $m)) {
                $base = strtoupper($m[2]);
                $ctfNum = $m[1];
            } elseif ($gametype && preg_match('/run_(cpm|vq3)/i', $gametype, $m)) {
                $base = strtoupper($m[1]);
            }

            // Demo physics: "CPM", "CPM.1", "VQ3.2", etc. — takes priority
            if ($physics) {
                $parts = explode('.', strtoupper($physics));
                $base = $parts[0] ?: $base;
                if (isset($parts[1])) {
                    $ctfNum = $parts[1];
                }
            }

            return [$base, $ctfNum];
        };

        foreach ($records as $record) {
            $recordName = $record->user ? $record->user->plain_name ?? $record->user->name : $record->name;
            if (empty($recordName)) continue;

            [$recordBase, $recordCtf] = $parsePhysics(null, $record->gametype);

            foreach ($demos as $demo) {
                [$demoBase, $demoCtf] = $parsePhysics($demo->physics, $demo->gametype);

                // Physics base must match (CPM vs VQ3)
                if ($demoBase !== $recordBase) continue;

                // If both have CTF numbers, they must match
                if ($recordCtf !== null && $demoCtf !== null && $recordCtf !== $demoCtf) continue;

                // Time must match exactly — without exact time match, assignment makes no sense
                if (!$demo->time_ms || $demo->time_ms !== $record->time) continue;

                $confidence = $nameMatcher->calculateConfidence($demo->player_name, $recordName);
                if ($confidence >= 20) {
                    $matches[$record->id][] = [
                        'demo_id' => $demo->id,
                        'player_name' => $demo->player_name,
                        'record_player_name' => $recordName,
                        'confidence' => $confidence,
                        'time_ms' => $demo->time_ms,
                        'filename' => $demo->original_filename,
                    ];
                }
            }

            // Sort matches by confidence descending
            if (isset($matches[$record->id])) {
                usort($matches[$record->id], fn($a, $b) => $b['confidence'] - $a['confidence']);
            }
        }

        return response()->json($matches);
    }

    public function flagNsfw($id)
    {
        if (!auth()->check()) {
            abort(403);
        }

        $map = Map::findOrFail($id);

        if ($map->is_nsfw) {
            return response()->json(['success' => false, 'message' => 'Already flagged as NSFW.']);
        }

        $map->is_nsfw = true;
        $map->save();

        return response()->json(['success' => true, 'message' => "Map \"{$map->name}\" flagged as NSFW."]);
    }

    public function unflagNsfw($id)
    {
        if (!auth()->check()) {
            abort(403);
        }

        $map = Map::findOrFail($id);
        $map->is_nsfw = false;
        $map->save();

        return response()->json(['success' => true, 'message' => "Map \"{$map->name}\" NSFW flag removed."]);
    }

    public function rateDifficulty(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $map = Map::findOrFail($id);

        MapDifficultyRating::updateOrCreate(
            ['map_id' => $map->id, 'user_id' => $request->user()->id],
            ['rating' => $request->rating]
        );

        // Return updated stats
        $ratings = MapDifficultyRating::where('map_id', $map->id)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();
        $total = array_sum($ratings);
        $avg = $total > 0
            ? round(array_sum(array_map(fn($r, $c) => $r * $c, array_keys($ratings), array_values($ratings))) / $total, 1)
            : null;

        return response()->json([
            'average' => $avg,
            'total' => $total,
            'distribution' => $ratings,
            'user_rating' => (int) $request->rating,
        ]);
    }

    /**
     * Attach approved community flags to paginated records
     */
    private function attachCommunityFlags($records)
    {
        if (!$records) return;

        $recordIds = [];
        $demoIds = [];

        foreach ($records as $record) {
            $recordIds[] = $record->id;
            if ($record->uploadedDemos) {
                foreach ($record->uploadedDemos as $demo) {
                    $demoIds[] = $demo->id;
                }
            }
        }

        if (empty($recordIds) && empty($demoIds)) return;

        $flags = RecordFlag::where('status', 'approved')
            ->where(function ($q) use ($recordIds, $demoIds) {
                $q->whereIn('record_id', $recordIds);
                if (!empty($demoIds)) {
                    $q->orWhereIn('demo_id', $demoIds);
                }
            })
            ->get();

        // Index flags by record_id and demo_id
        $flagsByRecord = $flags->whereNotNull('record_id')->groupBy('record_id');
        $flagsByDemo = $flags->whereNotNull('demo_id')->groupBy('demo_id');

        foreach ($records as $record) {
            $recordFlags = collect();

            // Flags directly on this record
            if (isset($flagsByRecord[$record->id])) {
                $recordFlags = $recordFlags->merge($flagsByRecord[$record->id]);
            }

            // Flags on demos attached to this record
            if ($record->uploadedDemos) {
                foreach ($record->uploadedDemos as $demo) {
                    if (isset($flagsByDemo[$demo->id])) {
                        $recordFlags = $recordFlags->merge($flagsByDemo[$demo->id]);
                    }
                }
            }

            // Deduplicate by flag_type (keep the one with highest count)
            $record->approved_flags = $recordFlags->groupBy('flag_type')->map(function ($group) {
                return $group->sortByDesc('flag_count')->first();
            })->values()->toArray();
        }
    }

    private function attachMapScores($records, string $mapname, string $physics, string $mode): void
    {
        if (!$records || $records->isEmpty()) return;

        $mddIds = $records->getCollection()->pluck('mdd_id')->unique()->toArray();
        if (empty($mddIds)) return;

        $scores = PlayerMapScore::where('mapname', $mapname)
            ->where('physics', $physics)
            ->where('mode', $mode)
            ->whereIn('mdd_id', $mddIds)
            ->get()
            ->keyBy('mdd_id');

        $records->getCollection()->transform(function ($record) use ($scores) {
            $score = $scores->get($record->mdd_id);
            $record->map_score = $score ? round($score->map_score, 2) : null;
            $record->reltime = $score ? round($score->reltime, 4) : null;
            $record->multiplier = $score ? round($score->multiplier, 4) : null;
            $record->is_outlier = $score ? $score->is_outlier : false;
            return $record;
        });
    }
}
