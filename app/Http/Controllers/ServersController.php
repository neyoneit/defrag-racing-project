<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Server;
use App\Models\Record;
use Illuminate\Support\Facades\DB;

class ServersController extends Controller
{
    public function index(Request $request) {
        $servers = Server::where('online', true)
            ->where('visible', true)
            ->with(['onlinePlayers.spectators'])
            ->orderBy('plain_name', 'asc')
            ->get();

        $servers->each(function ($server) use ($request) {
            $server->map = strtolower($server->map);
            $server->load('mapdata');

            // Add user's personal best time for this map if logged in
            if ($request->user() && $request->user()->mdd_id) {
                // Determine gametype from server's defrag setting
                $gametype = str_contains(strtolower($server->defrag), 'cpm') ? 'cpm' : 'vq3';

                // Try to get user's record for any gametype variation on this map
                $myRecord = Record::where('mapname', $server->map)
                    ->where('mdd_id', $request->user()->mdd_id)
                    ->where('gametype', 'like', "%{$gametype}%")
                    ->orderBy('time', 'ASC')
                    ->first();

                if ($myRecord) {
                    $server->mytime_time = $myRecord->time;

                    // Get rank: count how many unique players have faster times
                    $fasterRecords = DB::table('records')
                        ->select('mdd_id')
                        ->where('mapname', $server->map)
                        ->where('gametype', 'like', "%{$gametype}%")
                        ->where('time', '<', $myRecord->time)
                        ->distinct()
                        ->count();

                    // Get total unique players with records on this map
                    $totalPlayers = DB::table('records')
                        ->select('mdd_id')
                        ->where('mapname', $server->map)
                        ->where('gametype', 'like', "%{$gametype}%")
                        ->distinct()
                        ->count();

                    $server->myrank_position = $fasterRecords + 1;
                    $server->myrank_total = $totalPlayers;
                } else {
                    $server->mytime_time = null;
                    $server->myrank_position = null;
                    $server->myrank_total = null;
                }
            } else {
                $server->mytime_time = null;
                $server->myrank_position = null;
                $server->myrank_total = null;
            }
        });

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


