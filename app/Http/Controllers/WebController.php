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
use App\Models\Record;
use App\Models\PlayerRating;
use App\Models\PlayerModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WebController extends Controller
{
    /**
     * Recent commits for the desktop launcher, pulled from the GitHub API.
     * The launcher lives in a separate repo that isn't checked out on the
     * web server, so unlike the web changelog (read from local `git log`)
     * we have to fetch these over HTTP. Cached for an hour - GitHub's
     * unauthenticated limit is 60 req/h per IP and one call/hour stays
     * comfortably under it. Returns the same shape as the web changelog
     * (hash/title/description/date/author) so the frontend renders both
     * feeds with one template.
     */
    private function launcherCommits(int $limit = 100) {
        return Cache::remember("launcher_commits_v1_{$limit}", 3600, function () use ($limit) {
            try {
                $resp = Http::withHeaders([
                    'User-Agent' => 'defrag-racing-web',
                    'Accept' => 'application/vnd.github+json',
                ])->timeout(8)->get(
                    'https://api.github.com/repos/Defrag-racing/defrag-racing-launcher/commits',
                    ['per_page' => min($limit, 100)]
                );

                if (! $resp->ok()) return [];

                $commits = [];
                foreach ($resp->json() as $c) {
                    $message = $c['commit']['message'] ?? '';
                    $lines = explode("\n", $message);
                    $title = trim($lines[0] ?? '');
                    if ($title === '') continue;
                    if (str_starts_with($title, 'Merge pull request') || str_starts_with($title, 'Merge branch')) continue;

                    $body = trim(implode("\n", array_slice($lines, 1)));
                    $body = preg_replace('/\n*Co-Authored-By:.*$/s', '', $body);
                    $body = trim($body);

                    $rawDate = $c['commit']['author']['date'] ?? null;
                    $author = $c['commit']['author']['name'] ?? ($c['author']['login'] ?? '');

                    $commits[] = [
                        'hash' => substr($c['sha'] ?? '', 0, 7),
                        'title' => $title,
                        'description' => $body,
                        'date' => $rawDate ? date('Y-m-d', strtotime($rawDate)) : null,
                        'author' => $author,
                    ];
                }
                return $commits;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
    public function home() {
        $announcements = Cache::remember('home:announcements', 300, function () {
            $latest = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->first();
            $recent = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->skip(1)->limit(5)->get();
            return ['latest' => $latest, 'recent' => $recent];
        });

        $maps = Cache::remember('home:latest_maps', 600, function () {
            return Map::query()
                ->orderBy('date_added', 'DESC')
                ->orderBy('id', 'DESC')
                ->limit(4)
                ->get();
        });

        $totalMaps = Cache::remember('home:total_maps', 3600, fn () => Map::count());
        $totalDemos = Cache::remember('home:total_demos', 900, fn () => UploadedDemo::count());

        $tournaments = Cache::remember('home:tournaments', 600, function () {
            return Tournament::query()
                ->where('start_date', '<=', now())
                ->orderBy('start_date', 'DESC')
                ->limit(3)
                ->get();
        });

        // Servers change frequently (player joins/leaves), short TTL
        $serverData = Cache::remember('home:servers', 30, function () {
            $servers = Server::where('online', true)
                ->where('visible', true)
                ->with(['mapdata', 'onlinePlayers.spectators'])
                ->orderBy('plain_name', 'asc')
                ->get();

            $servers = $this->sortServers($servers);

            return [
                'activeServers' => $servers->count(),
                'servers' => $servers->values()->take(4),
            ];
        });

        $activePlayers = Cache::remember('home:active_players', 3600, function () {
            return Record::where('created_at', '>=', now()->subDays(30))->distinct('mdd_id')->count('mdd_id');
        });

        $totalRecords = Cache::remember('home:total_records', 3600, fn () => Record::count());

        // Recent world records (rank 1) - 3 VQ3 + 3 CPM
        $recentWorldRecords = Cache::remember('home:recent_wrs', 120, function () {
            $cols = ['id', 'mapname', 'mdd_id', 'name', 'time', 'physics', 'date_set', 'gametype'];
            $vq3 = Record::where('rank', 1)->where('physics', 'vq3')
                ->with('user:id,name,profile_photo_path,country,mdd_id,color')
                ->orderBy('date_set', 'DESC')->limit(3)->get($cols);
            $cpm = Record::where('rank', 1)->where('physics', 'cpm')
                ->with('user:id,name,profile_photo_path,country,mdd_id,color')
                ->orderBy('date_set', 'DESC')->limit(3)->get($cols);
            return $vq3->concat($cpm);
        });

        // Ranking highlights - top 3 VQ3 + top 3 CPM
        $rankingHighlights = Cache::remember('home:ranking_highlights', 3600, function () {
            $vq3 = PlayerRating::where('physics', 'vq3')
                ->where('mode', 'run')
                ->where('category', 'overall')
                ->where('active_players_rank', '>', 0)
                ->orderBy('active_players_rank', 'ASC')
                ->with('user:id,name,profile_photo_path,country,mdd_id,color')
                ->limit(3)
                ->get(['id', 'name', 'mdd_id', 'physics', 'active_players_rank', 'player_rating']);
            $cpm = PlayerRating::where('physics', 'cpm')
                ->where('mode', 'run')
                ->where('category', 'overall')
                ->where('active_players_rank', '>', 0)
                ->orderBy('active_players_rank', 'ASC')
                ->with('user:id,name,profile_photo_path,country,mdd_id,color')
                ->limit(3)
                ->get(['id', 'name', 'mdd_id', 'physics', 'active_players_rank', 'player_rating']);
            return ['vq3' => $vq3, 'cpm' => $cpm];
        });

        // Recent changelog (from git log, reuse roadmap cache)
        $recentChangelog = Cache::remember('home:changelog', 3600, function () {
            $format = '%H' . chr(30) . '%s' . chr(30) . '%b' . chr(30) . '%ai' . chr(31);
            $output = shell_exec("git log --format=\"$format\" -15 2>/dev/null");
            if (!$output) return [];

            $entries = explode(chr(31), $output);
            $commits = [];
            foreach ($entries as $entry) {
                $entry = trim($entry);
                if (!$entry) continue;
                $parts = explode(chr(30), $entry, 4);
                if (count($parts) < 2) continue;
                $title = trim($parts[1]);
                if (str_starts_with($title, 'Merge pull request') || str_starts_with($title, 'Merge branch')) continue;
                $body = trim($parts[2] ?? '');
                $body = preg_replace('/\n*Co-Authored-By:.*$/s', '', $body);
                $body = trim($body);
                $commits[] = [
                    'hash' => substr(trim($parts[0]), 0, 7),
                    'title' => $title,
                    'description' => $body,
                    'date' => isset($parts[3]) ? date('Y-m-d', strtotime(trim($parts[3]))) : null,
                ];
            }
            return array_slice($commits, 0, 5);
        });

        // Recent launcher changelog (GitHub API, separate repo)
        $recentChangelogLauncher = array_slice($this->launcherCommits(15), 0, 5);

        // Upcoming/current tournaments
        $upcomingTournaments = Cache::remember('home:upcoming_tournaments', 600, function () {
            return Tournament::where(function ($q) {
                $q->where('end_date', '>=', now())
                  ->orWhereNull('end_date');
            })
            ->orderBy('start_date', 'ASC')
            ->limit(3)
            ->get();
        });

        // Latest model with gesture gif
        $latestModel = Cache::remember('home:latest_model', 3600, function () {
            return PlayerModel::where('approval_status', 'approved')
                ->whereNotNull('gesture_gif')
                ->orderBy('created_at', 'DESC')
                ->first(['id', 'name', 'gesture_gif', 'thumbnail', 'idle_gif']);
        });

        // Latest map (already have $maps but get the first one)
        $latestMap = $maps->first();

        return Inertia::render('Home')
            ->with('latestAnnouncement', $announcements['latest'])
            ->with('recentAnnouncements', $announcements['recent'])
            ->with('maps', $maps)
            ->with('servers', $serverData['servers'])
            ->with('tournaments', $tournaments)
            ->with('totalMaps', $totalMaps)
            ->with('totalDemos', $totalDemos)
            ->with('activeServers', $serverData['activeServers'])
            ->with('activePlayers', $activePlayers)
            ->with('totalRecords', $totalRecords)
            ->with('recentWorldRecords', $recentWorldRecords)
            ->with('rankingHighlights', $rankingHighlights)
            ->with('recentChangelog', $recentChangelog)
            ->with('recentChangelogLauncher', $recentChangelogLauncher)
            ->with('upcomingTournaments', $upcomingTournaments)
            ->with('latestModel', $latestModel)
            ->with('latestMap', $latestMap);
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

            return redirect($url);
        }

        abort(404);
    }

    public function gettingstarted() {
        return Inertia::render('GettingStarted');
    }

    public function roadmap() {
        $commits = cache()->remember('roadmap_commits', 3600, function () {
            $format = '%H' . chr(30) . '%s' . chr(30) . '%b' . chr(30) . '%ai' . chr(30) . '%an' . chr(31);
            $output = shell_exec("git log --format=\"$format\" --reverse --since='2025-01-01' 2>/dev/null");
            if (!$output) return [];

            $entries = explode(chr(31), $output);
            $commits = [];
            foreach ($entries as $entry) {
                $entry = trim($entry);
                if (!$entry) continue;
                $parts = explode(chr(30), $entry, 5);
                if (count($parts) < 2) continue;

                $body = trim($parts[2] ?? '');
                $body = preg_replace('/\n*Co-Authored-By:.*$/s', '', $body);
                $body = trim($body);

                $date = isset($parts[3]) ? date('Y-m-d', strtotime(trim($parts[3]))) : null;
                $author = trim($parts[4] ?? '');

                $commits[] = [
                    'hash' => substr(trim($parts[0]), 0, 7),
                    'title' => trim($parts[1]),
                    'description' => $body,
                    'date' => $date,
                    'author' => $author,
                ];
            }
            return array_reverse($commits);
        });

        // Launcher commits (GitHub API). API returns newest-first; reverse
        // to oldest-first so the roadmap reads chronologically like the web list.
        $launcherCommits = array_reverse($this->launcherCommits(100));

        return Inertia::render('Roadmap', [
            'commits' => $commits,
            'launcherCommits' => $launcherCommits,
        ]);
    }
}
