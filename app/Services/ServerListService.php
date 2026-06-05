<?php

namespace App\Services;

use App\Models\Record;
use App\Models\Server;
use App\Models\User;
use App\Support\PingEstimator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the server-browser payload shared by the public web AJAX
 * endpoint (/api/servers/live) and the token-locked launcher endpoint
 * (/api/launcher/servers). Same data shape, same per-user enrichment
 * (mytime / myrank / besttime) — only the auth surface differs.
 *
 * Extracted from ServersController so any caller that has a Request
 * (web session or launcher Sanctum token both put a User on the
 * Request) can call ::list() and get back the exact JSON the SPA
 * already consumes.
 */
class ServerListService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(Request $request): array
    {
        $servers = Server::where('online', true)
            ->where('visible', true)
            ->with(['onlinePlayers.spectators', 'mapdata'])
            ->orderBy('plain_name', 'asc')
            ->get();

        $servers->each(function ($server) {
            $server->map = strtolower($server->map);
        });

        $mddId = $request->user()?->mdd_id;
        if ($mddId) {
            $mapGameTypes = $servers->map(fn ($s) => [
                'map' => $s->map,
                'gametype' => str_contains(strtolower($s->defrag), 'cpm') ? 'cpm' : 'vq3',
            ])->unique(fn ($item) => $item['map'].':'.$item['gametype']);

            $myRecords = collect();
            if ($mapGameTypes->isNotEmpty()) {
                $myRecords = Record::where('mdd_id', $mddId)
                    ->whereIn('mapname', $mapGameTypes->pluck('map')->unique())
                    ->orderBy('time', 'ASC')
                    ->get()
                    ->groupBy(fn ($r) => $r->mapname.':'.(str_contains($r->gametype, 'cpm') ? 'cpm' : 'vq3'));
            }

            $uniqueMaps = $mapGameTypes->pluck('map')->unique()->values()->all();
            $rankData = Cache::remember(
                'servers:ranks:'.md5(implode(',', $uniqueMaps)),
                60,
                function () use ($uniqueMaps) {
                    if (empty($uniqueMaps)) {
                        return collect();
                    }

                    return DB::table('records')
                        ->whereIn('mapname', $uniqueMaps)
                        ->whereNull('deleted_at')
                        ->select('mapname', 'gametype', 'mdd_id', DB::raw('MIN(time) as best_time'))
                        ->groupBy('mapname', 'gametype', 'mdd_id')
                        ->get()
                        ->groupBy(fn ($r) => $r->mapname.':'.(str_contains($r->gametype, 'cpm') ? 'cpm' : 'vq3'));
                }
            );

            $servers->each(function ($server) use ($myRecords, $rankData) {
                $gametype = str_contains(strtolower($server->defrag), 'cpm') ? 'cpm' : 'vq3';
                $key = $server->map.':'.$gametype;
                $myMapRecords = $myRecords->get($key);
                $myRecord = $myMapRecords?->first();

                if ($myRecord) {
                    $server->mytime_time = $myRecord->time;
                    $server->mytime_date = $myRecord->date_set;
                    $mapRanks = $rankData->get($key, collect());
                    $totalPlayers = $mapRanks->count();
                    $fasterPlayers = $mapRanks->filter(fn ($r) => $r->best_time < $myRecord->time)->count();
                    $server->myrank_position = $fasterPlayers + 1;
                    $server->myrank_total = $totalPlayers;
                } else {
                    $server->mytime_time = null;
                    $server->mytime_date = null;
                    $server->myrank_position = null;
                    $server->myrank_total = null;
                }
            });
        } else {
            $servers->each(function ($server) {
                $server->mytime_time = null;
                $server->mytime_date = null;
                $server->myrank_position = null;
                $server->myrank_total = null;
            });
        }

        $wrKeys = $servers->map(fn ($s) => [
            'mapname' => $s->map,
            'gametype' => str_contains(strtolower($s->defrag), 'cpm') ? 'cpm' : 'vq3',
            'time' => (int) $s->besttime_time,
        ])->filter(fn ($k) => $k['time'] > 0)->values();

        if ($wrKeys->isNotEmpty()) {
            $wrMaps = $wrKeys->pluck('mapname')->unique()->values()->all();
            $wrCandidates = Record::whereIn('mapname', $wrMaps)
                ->whereIn('time', $wrKeys->pluck('time')->unique()->values()->all())
                ->get(['mapname', 'gametype', 'time', 'date_set'])
                ->groupBy(fn ($r) => $r->mapname.':'.(str_contains($r->gametype, 'cpm') ? 'cpm' : 'vq3').':'.$r->time);

            $servers->each(function ($server) use ($wrCandidates) {
                if (! $server->besttime_time) {
                    $server->besttime_date = null;

                    return;
                }
                $gametype = str_contains(strtolower($server->defrag), 'cpm') ? 'cpm' : 'vq3';
                $key = $server->map.':'.$gametype.':'.(int) $server->besttime_time;
                $server->besttime_date = $wrCandidates->get($key)?->first()?->date_set;
            });
        } else {
            $servers->each(fn ($s) => $s->besttime_date = null);
        }

        $servers = $this->sortServers($servers);

        // Prefer linked user's profile country over whatever country was
        // stamped on the underlying record (which can be stale after a
        // location change).
        $besttimeUrls = $servers->pluck('besttime_url')->filter()->unique()->values()->all();
        if (! empty($besttimeUrls)) {
            $userCountries = User::whereIn('id', $besttimeUrls)
                ->pluck('country', 'id')
                ->toArray();

            $mddCountries = User::whereIn('mdd_id', $besttimeUrls)
                ->pluck('country', 'mdd_id')
                ->toArray();

            $servers->each(function ($server) use ($userCountries, $mddCountries) {
                if (! $server->besttime_url) {
                    return;
                }

                $userCountry = $userCountries[$server->besttime_url] ?? $mddCountries[$server->besttime_url] ?? null;
                if ($userCountry && $userCountry !== '_404' && $userCountry !== 'XX') {
                    $server->besttime_country = $userCountry;
                }
            });
        }

        // Visitor location (Cloudflare headers) for the per-server ping
        // estimate. Resolved once; null when the request didn't pass through
        // Cloudflare, in which case every estimated_ping comes back null and
        // the UI simply hides the badge.
        $visitor = PingEstimator::visitorLatLon($request);

        return $servers->values()->map(function ($server) use ($visitor) {
            $array = $server->toArray();
            $array['mytime_time'] = $server->mytime_time ?? null;
            $array['mytime_date'] = $server->mytime_date ?? null;
            $array['myrank_position'] = $server->myrank_position ?? null;
            $array['myrank_total'] = $server->myrank_total ?? null;
            $array['besttime_date'] = $server->besttime_date ?? null;
            // Base geo estimate, plus a small +/-5ms wobble each request so the
            // figure feels live (real pings jitter) instead of a frozen number.
            $base = $visitor
                ? PingEstimator::estimate($visitor[0], $visitor[1], $server->latitude, $server->longitude)
                : null;
            $array['estimated_ping'] = $base !== null ? max(1, $base + random_int(-5, 5)) : null;

            return $array;
        })->all();
    }

    private function sortServers($servers)
    {
        return $servers->sortByDesc(fn ($server) => $server->onlinePlayers->count());
    }
}
