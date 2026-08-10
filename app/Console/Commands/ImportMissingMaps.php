<?php

namespace App\Console\Commands;

use App\External\WorldSpawn;
use App\Models\Map;
use App\Models\UploadedDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds rows for maps that demos sit on but the maps table has never heard of.
 *
 * `scrape:maps` walks Worldspawn newest-first and stops at the newest map it
 * already knows, so it only ever picks up what was published since the last
 * run. Anything older than the first scrape stays missing forever: its page
 * 404s, it is absent from search and from the map list, and the demos on it
 * have nowhere to be seen. 1374 such maps carry 12362 demos.
 *
 * Worldspawn does not have all of them - the long tail of one-demo maps is
 * mostly gone, and stock Quake 3 maps were never there in the first place -
 * so whatever it cannot answer for is written out for a second pass from
 * another source.
 *
 * Reports without writing unless --apply is passed.
 */
class ImportMissingMaps extends Command
{
    protected $signature = 'maps:import-missing
        {--apply : Actually add the maps. Without this nothing is written}
        {--limit=0 : Stop after this many maps (0 = all)}
        {--min-demos=1 : Skip maps carrying fewer demos than this}
        {--sleep=1 : Seconds to wait between requests to Worldspawn}
        {--only=* : Only these map names, instead of everything missing}
        {--via-search : Also import maps only a search could find, under the name the demos use}';

    protected $description = 'Add maps that demos reference but the maps table is missing';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = (int) $this->option('limit');
        $minDemos = max(1, (int) $this->option('min-demos'));
        $sleep = max(0, (int) $this->option('sleep'));

        $missing = $this->missing($minDemos);

        if ($only = $this->option('only')) {
            $wanted = array_map('mb_strtolower', $only);
            $missing = $missing->filter(fn ($row) => in_array(mb_strtolower($row->map_name), $wanted, true))->values();
        }

        if ($missing->isEmpty()) {
            $this->info('Every map a demo points at already has a row.');

            return self::SUCCESS;
        }

        if ($limit > 0) {
            $missing = $missing->take($limit);
        }

        $this->info(($apply ? 'Importing ' : 'Dry run over ') . $missing->count() . ' map(s), '
            . $missing->sum('demos') . ' demo(s) between them.');
        $this->line('One request per map with ' . $sleep . 's between them, so this takes a while.');
        $this->newLine();

        $ws = new WorldSpawn();
        $found = 0;
        $added = 0;
        $absent = [];
        $candidates = [];

        foreach ($missing as $i => $row) {
            if ($i > 0 && $sleep > 0) {
                sleep($sleep);
            }

            $details = $this->lookUp($ws, $row->map_name);
            $viaSearch = null;

            if (! $details) {
                $viaSearch = $this->searchFor($ws, $row->map_name);

                if ($viaSearch) {
                    $details = $this->lookUp($ws, $viaSearch);
                }
            }

            if (! $details) {
                $absent[] = $row->demos . "\t" . $row->map_name;
                $this->line(sprintf('  %5d demos  %-34s not on Worldspawn', $row->demos, $row->map_name));
                continue;
            }

            if ($viaSearch) {
                // Published under a different name than the .bsp the demos
                // recorded. The row has to carry the name the demos use or they
                // still will not find it, so everything else comes from the
                // release page while the name does not.
                $candidates[] = $row->demos . "\t" . $row->map_name . "\t" . $viaSearch;
                $details['name'] = $row->map_name;

                if (! $this->option('via-search')) {
                    $this->line(sprintf(
                        '  %5d demos  %-34s only via search -> %s (skipped)',
                        $row->demos, $row->map_name, $viaSearch
                    ));
                    continue;
                }
            }

            $found++;

            if (! $apply) {
                $this->line(sprintf(
                    '  %5d demos  %-34s %s | %s | %s',
                    $row->demos,
                    $row->map_name,
                    $details['physics'] ?: '?',
                    $details['author'] ?: 'no author',
                    $details['weapons'] ?: 'no weapons'
                ));
                continue;
            }

            $added += $this->store($ws, $details) ? 1 : 0;
            $this->line(sprintf('  %5d demos  %-34s added', $row->demos, $row->map_name));
        }

        $this->newLine();
        $this->info('Looked at ' . $missing->count() . ' map(s).');
        $this->line('  Worldspawn has: ' . $found);
        $this->line('  Worldspawn does not have: ' . count($absent));

        if ($apply) {
            $this->line('  rows added: ' . $added);
        }

        if ($candidates) {
            $this->line('  found only by search: ' . count($candidates)
                . ($this->option('via-search') ? ' (imported)' : ' (skipped, pass --via-search to take them)'));

            $list = storage_path('logs/maps-found-by-search.txt');
            file_put_contents($list, implode("\n", $candidates) . "\n");
            $this->line('  listed in ' . $list . ' as demos, name used, name on Worldspawn');
        }

        if ($absent) {
            $list = storage_path('logs/maps-not-on-worldspawn.txt');
            file_put_contents($list, implode("\n", $absent) . "\n");
            $this->line('  written to ' . $list . ' for a second pass');
        }

        if (! $apply && $found > 0) {
            $this->newLine();
            $this->comment('Nothing was written. Add --apply to import these.');
        }

        return self::SUCCESS;
    }

    /**
     * Map names carried by demos that have no row, most demos first, compared
     * case-insensitively - `RUNDTC` is stored as `rundtc`.
     */
    private function missing(int $minDemos)
    {
        $have = Map::pluck('name')->map(fn ($n) => mb_strtolower($n))->flip();

        return UploadedDemo::query()
            ->whereNotNull('map_name')
            ->where('map_name', '!=', '')
            ->select('map_name', DB::raw('count(*) as demos'))
            ->groupBy('map_name')
            ->having('demos', '>=', $minDemos)
            ->orderByDesc('demos')
            ->get()
            ->reject(fn ($row) => isset($have[mb_strtolower($row->map_name)]))
            ->values();
    }

    /**
     * Worldspawn's details for a map, or null. Anything that goes wrong counts
     * as "not there": a missing map answers with a page that has none of the
     * elements the parser reaches for, so it fails on a null rather than
     * returning cleanly, and one absent map must not end the run.
     */
    private function lookUp(WorldSpawn $ws, string $name): ?array
    {
        try {
            $details = $ws->getMapDetails($name);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($details) && ! empty($details['name']) ? $details : null;
    }

    /**
     * The one map a search turns up for a name, or null.
     *
     * Worldspawn names its pages after the release, which is usually but not
     * always the .bsp inside - the demos on "thirdperson" belong to the map
     * published as "teamrun-thirdperson". A search finds it, but a search also
     * matches on substrings, so this only answers when exactly one result
     * carries the name looked for. Anything less certain is left for a human.
     */
    private function searchFor(WorldSpawn $ws, string $name): ?string
    {
        try {
            $hits = $ws->searchMaps($name);
        } catch (\Throwable $e) {
            return null;
        }

        $hits = array_values(array_filter(
            $hits,
            fn ($hit) => str_contains(mb_strtolower($hit), mb_strtolower($name))
        ));

        return count($hits) === 1 ? $hits[0] : null;
    }

    private function store(WorldSpawn $ws, array $details): bool
    {
        if (Map::where('name', $details['name'])->exists()) {
            return false;
        }

        // Worldspawn answers for a stock map with "Quake III: Arena.pk3" and the
        // size of the whole game, which is a statement of where the map comes
        // from rather than a file anyone can fetch. Left in, the download button
        // would point at dl.defrag.racing for a pk3 that was never there.
        // Empty rather than null: the column does not take null, and the
        // download button hides on either.
        if (stripos((string) ($details['pk3'] ?? ''), 'quake iii') !== false) {
            $details['pk3'] = '';
            $details['pk3_size'] = 0;
        }

        $map = new Map();
        $map->fill($details);
        $map->thumbnail = $ws->downloadImage($details['map_image']);
        $map->visible = true;
        $map->save();

        return true;
    }
}
