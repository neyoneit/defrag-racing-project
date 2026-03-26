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
use App\Models\MapDifficultyRating;

use App\Filters\MapFilters;
use App\Services\NameMatcher;

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
            ->orderBy('date_added', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate(28)
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
        $maps = $maps->paginate(28)->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            $paging = ['page' => $maps->lastPage()];
            return redirect()->route('maps.filters', array_merge($paging, $queries));
        }

        return Inertia::render('Maps')
            ->with('maps', $maps)
            ->with('queries', $queries);
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
            // Determine offline gametype based on map name (same logic as online records)
            // fc (fast caps) maps start with "actf" or "ctf", otherwise df (defrag)
            $offlineGametype = (str_starts_with($map->name, 'actf') || str_starts_with($map->name, 'ctf')) ? 'fc' : 'df';

            // For fast caps with CTF modes, filter by physics field containing the CTF mode
            // Physics format: "CPM.2.TR" or "VQ3.1" where the number is the CTF mode
            // $gametype from URL contains: run, ctf1, ctf2, etc.
            if ($offlineGametype === 'fc' && strpos($gametype, 'ctf') === 0) {
                // Specific CTF mode selected - filter by physics LIKE pattern
                // Extract CTF number from gametype (e.g., "ctf2" -> "2")
                $ctfNumber = substr($gametype, 3, 1);

                $cpmOfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', "CPM.{$ctfNumber}%")
                    ->with(['demo.suggestedUser', 'user', 'renderedVideos' => fn($q) => $q->visible()->latest()])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'cpmPage')
                    ->withQueryString();

                // Combine with assigned online demos
                $cpmOfflineRecords = $this->combineOfflineAndOnlineDemos(
                    $cpmOfflineRecords, $map->name, null, "CPM.{$ctfNumber}%", $column, $order, $allCpmRecordsByTime
                );

                $vq3OfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', "VQ3.{$ctfNumber}%")
                    ->with(['demo.suggestedUser', 'user', 'renderedVideos' => fn($q) => $q->visible()->latest()])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'vq3Page')
                    ->withQueryString();

                // Combine with assigned online demos
                $vq3OfflineRecords = $this->combineOfflineAndOnlineDemos(
                    $vq3OfflineRecords, $map->name, null, "VQ3.{$ctfNumber}%", $column, $order, $allVq3RecordsByTime
                );
            } elseif ($offlineGametype === 'fc') {
                // Fast caps map but no specific CTF mode selected (on "run" gametype)
                // Show all offline fast caps records regardless of CTF mode
                $cpmOfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', 'CPM%')
                    ->with(['demo.suggestedUser', 'user', 'renderedVideos' => fn($q) => $q->visible()->latest()])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'cpmPage')
                    ->withQueryString();

                // Combine with assigned online demos
                $cpmOfflineRecords = $this->combineOfflineAndOnlineDemos(
                    $cpmOfflineRecords, $map->name, null, 'CPM%', $column, $order, $allCpmRecordsByTime
                );

                $vq3OfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', 'VQ3%')
                    ->with(['demo.suggestedUser', 'user', 'renderedVideos' => fn($q) => $q->visible()->latest()])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'vq3Page')
                    ->withQueryString();

                // Combine with assigned online demos
                $vq3OfflineRecords = $this->combineOfflineAndOnlineDemos(
                    $vq3OfflineRecords, $map->name, null, 'VQ3%', $column, $order, $allVq3RecordsByTime
                );
            } else {
                // For df (defrag), physics can be "CPM" or "CPM.TR" (with timer reset)
                // Use LIKE to match both
                $cpmOfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', 'CPM%')
                    ->with(['demo.suggestedUser', 'user', 'renderedVideos' => fn($q) => $q->visible()->latest()])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'cpmPage')
                    ->withQueryString();

                // Combine with assigned online demos
                $cpmOfflineRecords = $this->combineOfflineAndOnlineDemos(
                    $cpmOfflineRecords, $map->name, null, 'CPM%', $column, $order, $allCpmRecordsByTime
                );

                $vq3OfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', 'VQ3%')
                    ->with(['demo.suggestedUser', 'user', 'renderedVideos' => fn($q) => $q->visible()->latest()])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'vq3Page')
                    ->withQueryString();

                // Combine with assigned online demos
                $vq3OfflineRecords = $this->combineOfflineAndOnlineDemos(
                    $vq3OfflineRecords, $map->name, null, 'VQ3%', $column, $order, $allVq3RecordsByTime
                );
            }
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
            ->with('difficultyRating', [
                'average' => $difficultyAvg,
                'total' => $difficultyTotal,
                'distribution' => $difficultyRatings,
                'user_rating' => $userDifficultyRating,
            ]);

    }

    /**
     * Combine offline records with assigned online demos for "Demos Top" section
     */
    private function combineOfflineAndOnlineDemos($offlineRecords, $mapName, $physics, $physicsPattern, $column, $order, $mainRecordIds = [])
    {
        if (!$offlineRecords) {
            return null;
        }

        // Get offline records collection
        $offlineItems = $offlineRecords->getCollection()->map(function ($record) {
            // Determine flag type based on gametype and validity
            // If validity_flag is set, use that as the flag
            // Otherwise: mdf/mfs/mfc = ONLINE, df/fs/fc = OFFLINE
            $flagType = 'OFFLINE'; // Default
            if ($record->validity_flag) {
                // Validity flag takes priority (e.g., "client_finish=false")
                $flagType = $record->validity_flag;
            } elseif ($record->gametype && str_starts_with($record->gametype, 'm')) {
                // Online demo (mdf, mfs, mfc)
                $flagType = 'ONLINE';
            }

            return (object) [
                'id' => $record->id,
                'time_ms' => $record->time_ms,
                'time' => $record->time,
                'player_name' => $record->player_name,
                'date_set' => $record->date_set,
                'demo' => $record->demo,
                'demo_id' => $record->demo_id,
                'record_id' => null,
                'user' => null,
                'country' => $record->demo?->country ?? $record->user?->country ?? '_404',
                'rank' => $record->rank,
                'is_online' => $record->gametype && str_starts_with($record->gametype, 'm'), // true for mdf/mfs/mfc
                'verification_type' => $flagType, // ONLINE/OFFLINE or validity flag
                'rendered_videos' => $record->renderedVideos,
            ];
        });

        // Get assigned online demos for this map/physics
        $onlineDemosQuery = UploadedDemo::where('map_name', $mapName)
            ->where('status', 'assigned')
            ->whereNotNull('record_id')
            ->with(['record.user', 'user']);

        // Apply physics filter
        if ($physicsPattern) {
            $onlineDemosQuery->where('physics', 'LIKE', $physicsPattern);
        } else {
            $onlineDemosQuery->where('physics', $physics);
        }

        // Exclude demos already shown in main records table (avoid duplicates)
        if (!empty($mainRecordIds)) {
            $onlineDemosQuery->whereNotIn('record_id', $mainRecordIds);
        }

        $onlineDemos = $onlineDemosQuery->get()->map(function ($demo) {
            // Determine which user to use: record owner takes priority
            // If record has a registered user, use that for avatar/effects
            // If record has no user (unregistered player), pass NULL for user so no avatar/effects show
            $user = null;
            $nameToDisplay = $demo->player_name;

            if ($demo->record && $demo->record->user) {
                // Record has a registered user - use their info for name, avatar, effects
                $user = $demo->record->user;
                $nameToDisplay = $demo->record->user->name;
            } elseif ($demo->record && $demo->record->name) {
                // Record has no registered user - use record's name, but NO avatar/effects
                $user = null;
                $nameToDisplay = $demo->record->name;
            }

            return (object) [
                'id' => $demo->id,
                'time_ms' => $demo->time_ms,
                'time' => $demo->time_ms,
                'player_name' => $demo->player_name,
                'name' => $nameToDisplay, // Override name for unregistered record owners
                'date_set' => $demo->record_date ?? $demo->created_at,
                'demo' => $demo,
                'demo_id' => null, // Online demos don't use demo_id
                'record_id' => $demo->record_id, // Has record_id since they're assigned
                'user' => $user, // Use record owner (assigned user) for avatar/effects/country
                'country' => $demo->record->country ?? $demo->country, // Use record's country
                'rank' => null, // Will be calculated after merge
                'is_online' => true, // Flag for online demos
                'verification_type' => 'verified', // Online demos assigned to records are verified
            ];
        });

        // Merge collections - use base collect() to avoid EloquentCollection's getKey() calls
        $combined = collect($offlineItems->all())->merge($onlineDemos);

        // Attach community flags BEFORE ranking (so flagged items get excluded from ranks)
        $allDemoIds = $combined->pluck('demo_id')->filter()->values()->toArray();
        $allRecordIds = $combined->pluck('record_id')->filter()->values()->toArray();

        if (!empty($allDemoIds) || !empty($allRecordIds)) {
            $flags = RecordFlag::where('status', 'approved')
                ->where(function ($q) use ($allRecordIds, $allDemoIds) {
                    if (!empty($allRecordIds)) {
                        $q->whereIn('record_id', $allRecordIds);
                    }
                    if (!empty($allDemoIds)) {
                        $q->orWhereIn('demo_id', $allDemoIds);
                    }
                })
                ->get();

            $flagsByDemo = $flags->whereNotNull('demo_id')->groupBy('demo_id');
            $flagsByRecord = $flags->whereNotNull('record_id')->groupBy('record_id');

            $combined = $combined->map(function ($item) use ($flagsByDemo, $flagsByRecord) {
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
        }

        // Recalculate ranks - skip items with validity flags or community flags
        $rank = 0;
        $combined = $combined->sortBy('time_ms')->values()->map(function ($item) use (&$rank) {
            $hasFlagIssue = !empty($item->approved_flags) ||
                ($item->verification_type && !in_array($item->verification_type, ['OFFLINE', 'ONLINE', 'verified']));

            if ($hasFlagIssue) {
                $item->rank = null;
            } else {
                $rank++;
                $item->rank = $rank;
            }
            return $item;
        });

        // Apply sort order
        if ($column === 'date_set') {
            $combined = $order === 'ASC'
                ? $combined->sortBy('date_set')->values()
                : $combined->sortByDesc('date_set')->values();
        }

        // Update paginator with combined data - use base Collection to avoid Eloquent's getKey() calls
        $offlineRecords->setCollection(collect($combined->all()));

        return $offlineRecords;
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
}
