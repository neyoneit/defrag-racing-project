<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Server;
use App\Models\Record;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ServersController extends Controller
{
    public function index(Request $request) {
        $servers = Server::where('online', true)
            ->where('visible', true)
            ->with(['onlinePlayers.spectators', 'mapdata'])
            ->orderBy('plain_name', 'asc')
            ->get();

        $servers->each(function ($server) {
            $server->map = strtolower($server->map);
        });

        // Batch-load user's personal records for all server maps at once
        $mddId = $request->user()?->mdd_id;
        if ($mddId) {
            $mapGameTypes = $servers->map(fn($s) => [
                'map' => $s->map,
                'gametype' => str_contains(strtolower($s->defrag), 'cpm') ? 'cpm' : 'vq3',
            ])->unique(fn($item) => $item['map'] . ':' . $item['gametype']);

            // Get user's best time for all maps in one query
            $myRecords = collect();
            if ($mapGameTypes->isNotEmpty()) {
                $myRecords = Record::where('mdd_id', $mddId)
                    ->whereIn('mapname', $mapGameTypes->pluck('map')->unique())
                    ->orderBy('time', 'ASC')
                    ->get()
                    ->groupBy(fn($r) => $r->mapname . ':' . (str_contains($r->gametype, 'cpm') ? 'cpm' : 'vq3'));
            }

            // Get rank data for all relevant maps in one query
            $uniqueMaps = $mapGameTypes->pluck('map')->unique()->values()->all();
            $rankData = Cache::remember('servers:ranks:' . md5(implode(',', $uniqueMaps)), 60, function () use ($uniqueMaps) {
                if (empty($uniqueMaps)) return collect();
                return DB::table('records')
                    ->whereIn('mapname', $uniqueMaps)
                    ->whereNull('deleted_at')
                    ->select('mapname', 'gametype', 'mdd_id', DB::raw('MIN(time) as best_time'))
                    ->groupBy('mapname', 'gametype', 'mdd_id')
                    ->get()
                    ->groupBy(fn($r) => $r->mapname . ':' . (str_contains($r->gametype, 'cpm') ? 'cpm' : 'vq3'));
            });

            $servers->each(function ($server) use ($myRecords, $rankData) {
                $gametype = str_contains(strtolower($server->defrag), 'cpm') ? 'cpm' : 'vq3';
                $key = $server->map . ':' . $gametype;
                $myMapRecords = $myRecords->get($key);
                $myRecord = $myMapRecords?->first();

                if ($myRecord) {
                    $server->mytime_time = $myRecord->time;
                    $mapRanks = $rankData->get($key, collect());
                    $totalPlayers = $mapRanks->count();
                    $fasterPlayers = $mapRanks->filter(fn($r) => $r->best_time < $myRecord->time)->count();
                    $server->myrank_position = $fasterPlayers + 1;
                    $server->myrank_total = $totalPlayers;
                } else {
                    $server->mytime_time = null;
                    $server->myrank_position = null;
                    $server->myrank_total = null;
                }
            });
        } else {
            $servers->each(function ($server) {
                $server->mytime_time = null;
                $server->myrank_position = null;
                $server->myrank_total = null;
            });
        }

        $servers = $this->sortServers($servers);

        // Convert to array and ensure mytime_time and rank fields are included
        $servers = $servers->values()->map(function($server) {
            $array = $server->toArray();
            $array['mytime_time'] = $server->mytime_time ?? null;
            $array['myrank_position'] = $server->myrank_position ?? null;
            $array['myrank_total'] = $server->myrank_total ?? null;
            return $array;
        })->all();

        return Inertia::render('Servers')->with('servers', $servers);
    }

    function sortServers($servers) {
        $servers = $servers->sortByDesc(function ($server) {
            return $server->onlinePlayers->count();
        });

        return $servers;
    }
}


