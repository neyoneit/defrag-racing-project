<?php

namespace App\Console\Commands;

use App\Models\Map;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateMapRankedFlags extends Command
{
    protected $signature = 'maps:update-ranked-flags';
    protected $description = 'Update is_ranked_vq3 and is_ranked_cpm flags on maps table';

    const MIN_MAP_TOTAL_PARTICIPATORS = 5;
    const MIN_TOP1_TIME = 500; // ms
    const MAX_TIED_WR_PLAYERS = 3; // 4+ players with same WR = free WR map = unranked

    public function handle(): void
    {
        foreach (['vq3', 'cpm'] as $physics) {
            // Get maps with enough players and WR above minimum time
            $mapStats = DB::table('records')
                ->whereNull('deleted_at')
                ->where('gametype', 'like', '%_' . $physics)
                ->select('mapname')
                ->selectRaw('COUNT(DISTINCT mdd_id) as participators')
                ->selectRaw('MIN(time) as top1_time')
                ->groupBy('mapname')
                ->havingRaw('participators >= ?', [self::MIN_MAP_TOTAL_PARTICIPATORS])
                ->havingRaw('top1_time >= ?', [self::MIN_TOP1_TIME])
                ->get();

            // Filter out "free WR" maps (4+ players tied at WR time)
            $candidateMapnames = $mapStats->pluck('mapname')->toArray();

            $freeWrMaps = [];
            if (!empty($candidateMapnames)) {
                // For each candidate, count how many players share the WR time
                $freeWrMaps = DB::table('records as r')
                    ->join(
                        DB::raw('(SELECT mapname, MIN(time) as wr_time FROM records WHERE deleted_at IS NULL AND gametype LIKE \'%_' . $physics . '\' GROUP BY mapname) as wr'),
                        function ($join) {
                            $join->on('r.mapname', '=', 'wr.mapname')
                                ->on('r.time', '=', 'wr.wr_time');
                        }
                    )
                    ->whereNull('r.deleted_at')
                    ->where('r.gametype', 'like', '%_' . $physics)
                    ->whereIn('r.mapname', $candidateMapnames)
                    ->groupBy('r.mapname')
                    ->havingRaw('COUNT(DISTINCT r.mdd_id) > ?', [self::MAX_TIED_WR_PLAYERS])
                    ->pluck('r.mapname')
                    ->toArray();
            }

            $rankedMapnames = array_diff($candidateMapnames, $freeWrMaps);

            $column = 'is_ranked_' . $physics;

            Map::query()->update([$column => false]);

            if (!empty($rankedMapnames)) {
                Map::whereIn('name', $rankedMapnames)->update([$column => true]);
            }

            $freeWrCount = count($freeWrMaps);
            $rankedCount = count($rankedMapnames);
            Log::info("Updated {$column}: {$rankedCount} maps ranked, {$freeWrCount} excluded (free WR)");
        }

        $this->info('Map ranked flags updated.');
    }
}
