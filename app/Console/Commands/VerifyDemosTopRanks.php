<?php

namespace App\Console\Commands;

use App\Http\Controllers\MapsController;
use App\Models\DemosTopRank;
use Illuminate\Console\Command;

/**
 * Read-only parity check: compares the materialized demos_top_ranks against
 * the live MapsController::buildUnifiedLeaderboard ranking, per group, and
 * reports any rank mismatch / missing / extra entry. Touches no data, so it's
 * safe to run on production after a backfill to confirm the table matches what
 * the web map detail would compute.
 *
 *   php artisan demos:verify-top-ranks --map=cityrocket
 *   php artisan demos:verify-top-ranks            (samples maps from the table)
 */
class VerifyDemosTopRanks extends Command
{
    protected $signature = 'demos:verify-top-ranks {--map= : Verify a single map} {--limit=30 : How many maps to sample when --map is absent}';

    protected $description = 'Verify materialized Demos Top ranks match the live controller (read-only)';

    public function handle(): int
    {
        // Sample the biggest fields first (most ranked rows ~= most-played
        // maps like cityrocket), not alphabetical no-name maps - that's where
        // parity matters most and where the clustering is hardest.
        $maps = $this->option('map')
            ? [$this->option('map')]
            : DemosTopRank::selectRaw('map_name, COUNT(*) as c')
                ->groupBy('map_name')
                ->orderByDesc('c')
                ->limit((int) $this->option('limit'))
                ->pluck('map_name')
                ->all();

        if (empty($maps)) {
            $this->warn('No maps in demos_top_ranks. Run demos:rebuild-top-ranks first.');
            return self::SUCCESS;
        }

        $controller = app(MapsController::class);
        $method = new \ReflectionMethod($controller, 'buildUnifiedLeaderboard');
        $method->setAccessible(true);

        $grandMismatch = 0;
        foreach ($maps as $map) {
            $groups = DemosTopRank::where('map_name', $map)
                ->select('group_gametype', 'physics', 'physics_pattern')
                ->distinct()
                ->get();

            $mapMismatch = 0;
            foreach ($groups as $g) {
                $pageName = $g->physics === 'CPM' ? 'cpmPage' : 'vq3Page';

                // Live controller ranks (loop all pages).
                $ctrl = [];
                for ($p = 1; $p <= 200; $p++) {
                    request()->merge([$pageName => $p]);
                    $pag = $method->invoke($controller, $map, $g->group_gametype, $g->physics_pattern, true, false, 'time', 'ASC', $pageName);
                    foreach ($pag as $it) {
                        $st = $it->source_type ?? 'main';
                        if ($st === 'oldtop') continue;
                        $key = $st === 'main' ? 'main:' . $it->id : 'demo:' . ($it->demo_id ?? $it->id);
                        $ctrl[$key] = $it->rank;
                    }
                    if ($p >= $pag->lastPage()) break;
                }

                // Materialized ranks.
                $mine = [];
                foreach (DemosTopRank::where('map_name', $map)->where('group_gametype', $g->group_gametype)->get() as $r) {
                    $key = $r->entry_type === 'main' ? 'main:' . $r->record_id : 'demo:' . $r->uploaded_demo_id;
                    $mine[$key] = $r->rank;
                }

                $mis = 0;
                foreach ($ctrl as $k => $rk) {
                    if (!array_key_exists($k, $mine) || (string) $mine[$k] !== (string) $rk) $mis++;
                }
                $mis += count(array_diff_key($mine, $ctrl));
                $mapMismatch += $mis;
            }

            $grandMismatch += $mapMismatch;
            $flag = $mapMismatch === 0 ? '<info>OK</info>' : "<error>{$mapMismatch} mismatch</error>";
            $this->line(sprintf('  %-24s %s', $map, $flag));
        }

        $this->newLine();
        if ($grandMismatch === 0) {
            $this->info("PARITY OK across " . count($maps) . " maps - materialized ranks match the live controller.");
            return self::SUCCESS;
        }

        $this->error("Total mismatches: {$grandMismatch}. The table is stale or diverged - rebuild and investigate.");
        return self::FAILURE;
    }
}
