<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Record;
use App\Models\OldtopRecord;
use App\Models\OfflineRecord;
use App\Models\User;
use App\Models\Map;
use App\Models\MddProfile;

use App\Filters\MapFilters;

class MapsController extends Controller
{
    public function index(Request $request) {
        // If there are any filter parameters (except page), redirect to filters route
        $filterParams = $request->except(['page']);
        if (!empty($filterParams)) {
            return redirect()->route('maps.filters', $request->all());
        }

        $mddProfiles = MddProfile::orderBy('id', 'DESC')
            ->with('user:id,name,plain_name,country')
            ->get(['id', 'user_id', 'name', 'country', 'plain_name']);

        $maps = Map::query()
            ->orderBy('date_added', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate(30)
            ->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        return Inertia::render('Maps')->with('maps', $maps)->with('profiles', $mddProfiles);
    }

    public function filters(Request $request) {
        $mddProfiles = MddProfile::orderBy('id', 'DESC')
            ->with('user:id,name,plain_name,country')
            ->get(['id', 'user_id', 'name', 'country', 'plain_name']);

        
        $mapFilters = (new MapFilters())->filter($request);

        $maps = $mapFilters['query'];

        $maps = $maps->paginate(30)->withQueryString();

        $queries = $mapFilters['data'];

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            $paging = ['page' => $maps->lastPage()];

            return redirect()->route('maps.filters', array_merge($paging, $queries));
        }

        return Inertia::render('Maps')
            ->with('maps', $maps)
            ->with('queries', $queries)
            ->with('profiles', $mddProfiles);
    }

    public function map(Request $request, $mapname) {
        $column = $request->input('sort', 'time');
        $order = $request->input('order', 'ASC');

        $map = Map::where('name', $mapname)->with('tags')->firstOrFail();

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

        $cpmRecords = $cpmRecords->with(['user', 'uploadedDemos'])->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'cpmPage')->withQueryString();

        // Assign time-based ranks (always based on fastest time, not current sort)
        $cpmRecords->getCollection()->transform(function ($record) use ($allCpmRecordsByTime) {
            $record->rank = array_search($record->id, $allCpmRecordsByTime) + 1;
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

        $vq3Records = $vq3Records->with(['user', 'uploadedDemos'])->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'vq3Page')->withQueryString();

        // Assign time-based ranks (always based on fastest time, not current sort)
        $vq3Records->getCollection()->transform(function ($record) use ($allVq3RecordsByTime) {
            $record->rank = array_search($record->id, $allVq3RecordsByTime) + 1;
            return $record;
        });


        $showOldtop = $request->input('showOldtop', false);

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
        $showOffline = $request->input('showOffline', false);

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
                    ->with(['demo', 'user'])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'cpmPage')
                    ->withQueryString();

                $vq3OfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', "VQ3.{$ctfNumber}%")
                    ->with(['demo', 'user'])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'vq3Page')
                    ->withQueryString();
            } elseif ($offlineGametype === 'fc') {
                // Fast caps map but no specific CTF mode selected (on "run" gametype)
                // Show all offline fast caps records regardless of CTF mode
                $cpmOfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', 'CPM%')
                    ->with(['demo', 'user'])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'cpmPage')
                    ->withQueryString();

                $vq3OfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'LIKE', 'VQ3%')
                    ->with(['demo', 'user'])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'vq3Page')
                    ->withQueryString();
            } else {
                // For df (defrag), physics is just "CPM" or "VQ3" (no CTF mode)
                $cpmOfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'CPM')
                    ->with(['demo', 'user'])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'cpmPage')
                    ->withQueryString();

                $vq3OfflineRecords = OfflineRecord::where('map_name', $map->name)
                    ->where('physics', 'VQ3')
                    ->with(['demo', 'user'])
                    ->orderBy($column === 'date_set' ? 'date_set' : 'time_ms', $order)
                    ->paginate(50, ['*'], 'vq3Page')
                    ->withQueryString();
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
            ->with('publicMaplists', $publicMaplists);

    }
}
