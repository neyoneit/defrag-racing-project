<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Announcement;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\Map;
use App\Models\Server;
use App\Models\Tournament;
use App\Models\MddProfile;
use App\Models\UploadedDemo;
use Illuminate\Support\Facades\DB;

class WebController extends Controller
{
    public function home() {
        $latestAnnouncement = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->first();
        $recentAnnouncements = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->skip(1)->limit(5)->get();

        $maps = Map::query()
            ->orderBy('date_added', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(4)
            ->get();

        $totalMaps = Map::count();
        $totalDemos = UploadedDemo::count();

        $tournaments = Tournament::query()
            ->where('start_date', '<=', now())
            ->orderBy('start_date', 'DESC')
            ->limit(3)
            ->get();

        $servers = Server::where('online', true)
            ->where('visible', true)
            ->with(['mapdata', 'onlinePlayers.spectators'])
            ->orderBy('plain_name', 'asc')
            ->get();

        $servers = $this->sortServers($servers);

        $activeServers = $servers->count();
        $servers = $servers->values()->take(3);

        // Count active players in last 30 days (using updated_at as proxy for activity)
        $activePlayers = MddProfile::where('updated_at', '>=', now()->subDays(30))->count();

        return Inertia::render('Home')
            ->with('latestAnnouncement', $latestAnnouncement)
            ->with('recentAnnouncements', $recentAnnouncements)
            ->with('maps', $maps)
            ->with('servers', $servers)
            ->with('tournaments', $tournaments)
            ->with('totalMaps', $totalMaps)
            ->with('totalDemos', $totalDemos)
            ->with('activeServers', $activeServers)
            ->with('activePlayers', $activePlayers);
    }

    function sortServers($servers) {
        $servers = $servers->sortByDesc(function ($server) {
            return $server->onlinePlayers->count();
        });

        return $servers;
    }

    public function flags($flag) {
        $fileResponse = new BinaryFileResponse(public_path() . '/images/flags/_404.png');

        return $fileResponse;
    }

    public function thumbs($image) {
        return response()->file(public_path() . '/images/unknown.jpg');

        return $fileResponse;
    }

    public function map($name) {
        $map = Map::where('name', $name)->first();

        if ($map && $map->pk3) {
            $parts = explode('/', $map->pk3);
            $filename = end($parts);

            $url = "https://dl.defrag.racing/downloads/maps/" . $filename;

            $temp = tempnam(sys_get_temp_dir(), $filename);
            copy($url, $temp);


            return response()->download($temp, $filename)->deleteFileAfterSend(true);
        }

        abort(404);
    }

    public function gettingstarted() {
        return Inertia::render('GettingStarted');
    }

    public function roadmap() {
        return Inertia::render('Roadmap');
    }
}
