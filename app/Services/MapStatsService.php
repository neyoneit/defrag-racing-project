<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates for the public /maps/stats page and the nightly snapshot
 * export. Every method that touches `records` (646k rows) or runs a JOIN
 * with `maps` (15k) is wrapped in Cache::remember — once a key is warm,
 * the page renders in <50ms regardless of DB load.
 *
 * Cache TTL is 6 hours: a single new top-1 record per day moves the
 * scatter plots by less than a pixel, so a stale window of a few hours
 * is never noticeable to a visitor. The nightly artisan command bypasses
 * the cache and rebuilds the snapshot directly.
 */
class MapStatsService
{
    // 25h TTL paired with the daily 04:00 cron `mapstats:rebuild` —
    // the cron always refreshes the keys before they can expire, so
    // a visitor never lands on an empty cache that would force a
    // ~100s synchronous DB rebuild (production timing — local is
    // ~14s but production sees 7× slower aggregates on 646k rows).
    // 6h was the original TTL, but with rebuild taking that long any
    // mid-day expiry meant the next visitor stalled / 504'd from the
    // upstream timeout.
    public const CACHE_TTL = 90000; // 25 hours
    public const CACHE_PREFIX = 'mapstats:';

    /**
     * Top-1 (fastest) record per (mapname, physics) joined with the map's
     * release date. Each row is one dot in the big scatter charts.
     *
     * Why MIN(time) and not rank=1: the records table tracks separate
     * ranks per gametype (run / ctf1..3), so a single map can have
     * multiple rank=1 rows. We want the absolute fastest run per map
     * per physics, irrespective of gametype.
     *
     * @return array<int, array{map:string,author:?string,holder:string,wr_ms:int,date_added:string,date_set:string,finishers:int,physics:string}>
     */
    public function wrPoints(string $physics): array
    {
        return Cache::remember(self::CACHE_PREFIX."wr_points:$physics", self::CACHE_TTL, function () use ($physics) {
            $rows = DB::select("
                SELECT r.mapname, r.time, r.date_set, r.name AS holder,
                       m.date_added, m.author
                FROM records r
                INNER JOIN (
                    SELECT mapname, MIN(time) AS best_time
                    FROM records
                    WHERE deleted_at IS NULL AND physics = ?
                    GROUP BY mapname
                ) b ON b.mapname = r.mapname AND b.best_time = r.time
                INNER JOIN maps m ON m.name = r.mapname
                WHERE r.deleted_at IS NULL AND r.physics = ?
                GROUP BY r.mapname
            ", [$physics, $physics]);

            $finishers = $this->finishersByMap($physics);

            $out = [];
            foreach ($rows as $r) {
                if (!$r->date_added || !$r->date_set) continue;
                $out[] = [
                    'map'        => $r->mapname,
                    'author'     => $r->author,
                    'holder'     => preg_replace('/\^[0-9a-fA-F]/', '', $r->holder ?? ''),
                    'wr_ms'      => (int) $r->time,
                    'date_added' => substr((string) $r->date_added, 0, 10),
                    'date_set'   => substr((string) $r->date_set, 0, 10),
                    'finishers'  => (int) ($finishers[$r->mapname] ?? 0),
                    'physics'    => $physics,
                ];
            }
            return $out;
        });
    }

    /**
     * Distinct mdd_id finisher count per map for the given physics. Counts
     * how many unique players have at least one record on that map —
     * better signal of popularity than total record count (which would
     * inflate when one player spams resubmissions).
     *
     * @return array<string, int>
     */
    public function finishersByMap(string $physics): array
    {
        return Cache::remember(self::CACHE_PREFIX."finishers:$physics", self::CACHE_TTL, function () use ($physics) {
            return DB::table('records')
                ->whereNull('deleted_at')
                ->where('physics', $physics)
                ->whereNotNull('mdd_id')
                ->groupBy('mapname')
                ->select('mapname', DB::raw('COUNT(DISTINCT mdd_id) AS finishers'))
                ->pluck('finishers', 'mapname')
                ->map(fn ($v) => (int) $v)
                ->all();
        });
    }

    /**
     * Map releases per year. Anything before 1999 is folded into "1999"
     * — early Q3 maps occasionally have nonsense dates from filesystem
     * mtime guessing.
     *
     * @return array<int, array{year:int,count:int}>
     */
    public function mapsPerYear(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'maps_per_year', self::CACHE_TTL, function () {
            return DB::table('maps')
                ->whereNotNull('date_added')
                ->select(DB::raw('YEAR(date_added) AS year'), DB::raw('COUNT(*) AS count'))
                ->groupBy('year')
                ->orderBy('year')
                ->get()
                ->map(fn ($r) => ['year' => (int) $r->year, 'count' => (int) $r->count])
                ->filter(fn ($r) => $r['year'] >= 1999 && $r['year'] <= (int) date('Y'))
                ->values()
                ->all();
        });
    }

    /**
     * Author productivity — top N most prolific mappers by visible map
     * count. Authors with empty/null names are dropped (legacy imports).
     *
     * @return array<int, array{author:string,count:int}>
     */
    public function topAuthors(int $limit = 30): array
    {
        return Cache::remember(self::CACHE_PREFIX."top_authors:$limit", self::CACHE_TTL, function () use ($limit) {
            return DB::table('maps')
                ->whereNotNull('author')
                ->where('author', '!=', '')
                ->select('author', DB::raw('COUNT(*) AS count'))
                ->groupBy('author')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->map(fn ($r) => ['author' => (string) $r->author, 'count' => (int) $r->count])
                ->all();
        });
    }

    /**
     * WRs held by country (uses the player's country at the time of
     * setting the record). Choropleth-friendly. Empty / 'XX' codes are
     * dropped.
     *
     * @return array<int, array{country:string,wrs:int}>
     */
    public function wrsByCountry(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'wrs_by_country', self::CACHE_TTL, function () {
            // Same min-time-per-map-per-physics dance as wrPoints, but
            // pulling the country instead of the time.
            $rows = DB::select("
                SELECT r.country
                FROM records r
                INNER JOIN (
                    SELECT mapname, physics, MIN(time) AS best_time
                    FROM records
                    WHERE deleted_at IS NULL
                    GROUP BY mapname, physics
                ) b ON b.mapname = r.mapname AND b.physics = r.physics AND b.best_time = r.time
                WHERE r.deleted_at IS NULL
                GROUP BY r.mapname, r.physics
            ");

            $counts = [];
            foreach ($rows as $r) {
                $c = strtoupper(trim((string) $r->country));
                if ($c === '' || $c === 'XX' || strlen($c) !== 2) continue;
                $counts[$c] = ($counts[$c] ?? 0) + 1;
            }
            arsort($counts);
            $out = [];
            foreach ($counts as $c => $n) $out[] = ['country' => $c, 'wrs' => $n];
            return $out;
        });
    }

    /**
     * Pareto-style WR concentration — what share of all WRs is held by
     * the top N players (by mdd_id). Useful for "is the scene healthy or
     * dominated by a handful of monsters".
     *
     * @return array<int, array{name:string,wrs:int,cumulative_pct:float}>
     */
    public function wrConcentration(int $limit = 30): array
    {
        return Cache::remember(self::CACHE_PREFIX."wr_concentration:$limit", self::CACHE_TTL, function () use ($limit) {
            $rows = DB::select("
                SELECT r.name, r.mdd_id
                FROM records r
                INNER JOIN (
                    SELECT mapname, physics, MIN(time) AS best_time
                    FROM records
                    WHERE deleted_at IS NULL
                    GROUP BY mapname, physics
                ) b ON b.mapname = r.mapname AND b.physics = r.physics AND b.best_time = r.time
                WHERE r.deleted_at IS NULL
                GROUP BY r.mapname, r.physics
            ");

            $perPlayer = [];
            $total = 0;
            foreach ($rows as $r) {
                $key = (int) ($r->mdd_id ?? 0);
                if ($key === 0) continue;
                $perPlayer[$key]['count'] = ($perPlayer[$key]['count'] ?? 0) + 1;
                $perPlayer[$key]['name']  = preg_replace('/\^[0-9a-fA-F]/', '', (string) $r->name);
                $total++;
            }
            uasort($perPlayer, fn ($a, $b) => $b['count'] <=> $a['count']);

            $out = [];
            $cum = 0;
            $i = 0;
            foreach ($perPlayer as $p) {
                $cum += $p['count'];
                $out[] = [
                    'name'           => $p['name'],
                    'wrs'            => $p['count'],
                    'cumulative_pct' => $total > 0 ? round($cum * 100 / $total, 2) : 0,
                ];
                if (++$i >= $limit) break;
            }
            return $out;
        });
    }

    /**
     * GitHub-style activity heatmap — count of records set per month
     * across the whole DB. Used for the calendar-style heatmap on the
     * page.
     *
     * @return array<int, array{ym:string,count:int}>
     */
    public function activityByMonth(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'activity_by_month', self::CACHE_TTL, function () {
            return DB::table('records')
                ->whereNull('deleted_at')
                ->whereNotNull('date_set')
                ->select(DB::raw("DATE_FORMAT(date_set, '%Y-%m') AS ym"), DB::raw('COUNT(*) AS count'))
                ->groupBy('ym')
                ->orderBy('ym')
                ->get()
                ->map(fn ($r) => ['ym' => (string) $r->ym, 'count' => (int) $r->count])
                ->all();
        });
    }

    /**
     * Distinct active players per year — players who set at least one
     * record in that calendar year. Tracks community health.
     *
     * @return array<int, array{year:int,players:int}>
     */
    public function activePlayersByYear(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'active_players_by_year', self::CACHE_TTL, function () {
            return DB::table('records')
                ->whereNull('deleted_at')
                ->whereNotNull('date_set')
                ->whereNotNull('mdd_id')
                ->select(DB::raw('YEAR(date_set) AS year'), DB::raw('COUNT(DISTINCT mdd_id) AS players'))
                ->groupBy('year')
                ->orderBy('year')
                ->get()
                ->map(fn ($r) => ['year' => (int) $r->year, 'players' => (int) $r->players])
                ->filter(fn ($r) => $r['year'] >= 1999 && $r['year'] <= (int) date('Y'))
                ->values()
                ->all();
        });
    }

    /**
     * Weapon presence in maps over time. For each year, what fraction
     * of new maps include each weapon — counts a map once per weapon
     * present, so a `gl,pg,rl` map increments grenade + plasma +
     * rocket all by 1.
     *
     * The `weapons` column stores comma-separated abbreviations
     * (`rl,pg,gl,lg,rg,sg,bfg,hook,gauntlet,mg`), not full names. Split
     * on commas and match exact tokens to avoid false positives like
     * `gauntlet` matching "g" or `pg` matching "p". Token list mirrors
     * the actual data: rare tokens (`ng`, `cg`, `pml`, `ga` — under
     * 30 maps each across the entire 15k library) are dropped from
     * the chart but still counted toward `total` so percentages are
     * accurate.
     *
     * @return array<int, array<string,int>>
     */
    public function weaponsByYear(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'weapons_by_year', self::CACHE_TTL, function () {
            // Map[token] => display key. Order matters: this is how
            // they appear left-to-right in the legend & stack.
            $tokenMap = [
                'rl'       => 'rocket',
                'pg'       => 'plasma',
                'gl'       => 'grenade',
                'rg'       => 'rail',
                'sg'       => 'shotgun',
                'bfg'      => 'bfg',
                'lg'       => 'lg',
                'hook'     => 'hook',
                'gauntlet' => 'gauntlet',
                'mg'       => 'machinegun',
            ];

            $rows = DB::table('maps')
                ->whereNotNull('date_added')
                ->select(DB::raw('YEAR(date_added) AS year'), 'weapons')
                ->get();

            $byYear = [];
            foreach ($rows as $r) {
                $y = (int) $r->year;
                if ($y < 1999 || $y > (int) date('Y')) continue;
                if (!isset($byYear[$y])) {
                    $byYear[$y] = ['year' => $y, 'total' => 0];
                    foreach ($tokenMap as $key) $byYear[$y][$key] = 0;
                }
                $byYear[$y]['total']++;
                $tokens = array_map('trim', explode(',', strtolower((string) ($r->weapons ?? ''))));
                foreach ($tokenMap as $tok => $key) {
                    if (in_array($tok, $tokens, true)) $byYear[$y][$key]++;
                }
            }
            ksort($byYear);
            return array_values($byYear);
        });
    }

    /**
     * Records-per-player distribution — how skewed is the player base.
     * Returns histogram buckets (1, 2-5, 6-10, 11-50, 51-100, 100+).
     *
     * @return array<int, array{bucket:string,players:int}>
     */
    public function recordsPerPlayer(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'records_per_player', self::CACHE_TTL, function () {
            $counts = DB::table('records')
                ->whereNull('deleted_at')
                ->whereNotNull('mdd_id')
                ->select('mdd_id', DB::raw('COUNT(*) AS n'))
                ->groupBy('mdd_id')
                ->get();

            $buckets = ['1' => 0, '2-5' => 0, '6-10' => 0, '11-50' => 0, '51-100' => 0, '100+' => 0];
            foreach ($counts as $c) {
                $n = (int) $c->n;
                if      ($n <= 1)   $buckets['1']++;
                elseif  ($n <= 5)   $buckets['2-5']++;
                elseif  ($n <= 10)  $buckets['6-10']++;
                elseif  ($n <= 50)  $buckets['11-50']++;
                elseif  ($n <= 100) $buckets['51-100']++;
                else                 $buckets['100+']++;
            }
            $out = [];
            foreach ($buckets as $k => $v) $out[] = ['bucket' => $k, 'players' => $v];
            return $out;
        });
    }

    /**
     * Top-level summary numbers shown above the charts. All cheap.
     *
     * @return array{total_maps:int,total_records:int,total_wrs:int,total_authors:int,total_players:int,first_record:?string,last_record:?string}
     */
    public function summary(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'summary', self::CACHE_TTL, function () {
            $totalMaps    = (int) DB::table('maps')->count();
            $totalRecords = (int) DB::table('records')->whereNull('deleted_at')->count();
            $totalAuthors = (int) DB::table('maps')->whereNotNull('author')->where('author', '!=', '')->distinct()->count('author');
            $totalPlayers = (int) DB::table('records')->whereNull('deleted_at')->whereNotNull('mdd_id')->distinct()->count('mdd_id');
            $totalWrs     = (int) DB::scalar("
                SELECT COUNT(*) FROM (
                    SELECT mapname, physics FROM records
                    WHERE deleted_at IS NULL
                    GROUP BY mapname, physics
                ) t
            ");
            $firstRecord  = DB::table('records')->whereNull('deleted_at')->whereNotNull('date_set')->min('date_set');
            $lastRecord   = DB::table('records')->whereNull('deleted_at')->whereNotNull('date_set')->max('date_set');

            return [
                'total_maps'    => $totalMaps,
                'total_records' => $totalRecords,
                'total_wrs'     => $totalWrs,
                'total_authors' => $totalAuthors,
                'total_players' => $totalPlayers,
                'first_record'  => $firstRecord ? substr((string) $firstRecord, 0, 10) : null,
                'last_record'   => $lastRecord  ? substr((string) $lastRecord, 0, 10) : null,
            ];
        });
    }

    /**
     * Bundle every chart into a single payload for the front-end.
     * `generated_at` is stamped once and stored alongside the payload so
     * the value is stable between requests within a cache window — this
     * lets the API endpoint emit a stable ETag for 304 short-circuiting.
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'all', self::CACHE_TTL, fn () => [
            'summary'              => $this->summary(),
            'cpm'                  => $this->wrPoints('cpm'),
            'vq3'                  => $this->wrPoints('vq3'),
            'maps_per_year'        => $this->mapsPerYear(),
            'top_authors'          => $this->topAuthors(),
            'wrs_by_country'       => $this->wrsByCountry(),
            'wr_concentration'     => $this->wrConcentration(),
            'activity_by_month'    => $this->activityByMonth(),
            'active_players_year'  => $this->activePlayersByYear(),
            'weapons_by_year'      => $this->weaponsByYear(),
            'records_per_player'   => $this->recordsPerPlayer(),
            'generated_at'         => now()->toIso8601String(),
        ]);
    }

    /**
     * Drop every cache key this service controls. Called by the nightly
     * snapshot command and from tinker when admins want a manual rebuild.
     */
    public function clearCache(): void
    {
        $keys = [
            'wr_points:cpm', 'wr_points:vq3',
            'finishers:cpm', 'finishers:vq3',
            'maps_per_year', 'top_authors:30',
            'wrs_by_country', 'wr_concentration:30',
            'activity_by_month', 'active_players_by_year',
            'weapons_by_year', 'records_per_player', 'summary',
            'all',
        ];
        foreach ($keys as $k) Cache::forget(self::CACHE_PREFIX.$k);
    }
}
