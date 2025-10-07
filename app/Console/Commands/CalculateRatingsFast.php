<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PlayerRating;

class CalculateRatingsFast extends Command
{
    protected $signature = 'ratings:calculate-fast {--physics= : vq3 or cpm} {--mode=run : game mode}';
    protected $description = 'Optimized ratings calculation - processes physics/mode separately';

    // Copy constants from CalculateRatings job
    const MIN_MAP_TOTAL_PARTICIPATORS = 5;
    const MIN_TOP1_TIME = 500;
    const MIN_TOP_RELTIME = 0.6;
    const MIN_TOTAL_RECORDS = 10;
    const ACTIVE_PLAYERS_MONTHS = 3;
    const BANNED_MAPS = ['map1', 'map2', 'map3'];
    const CFG_A = 1.5;
    const CFG_B = 2.086;
    const CFG_M = 0.3;
    const CFG_V = 0.1;
    const CFG_Q = 0.5;
    const CFG_D = 0.02;

    public function handle()
    {
        $physics = $this->option('physics');
        $mode = $this->option('mode');

        if (!$physics) {
            $this->error('Please specify --physics=vq3 or --physics=cpm');
            return 1;
        }

        $this->info("Calculating ratings for {$physics} {$mode}...");
        $startTime = microtime(true);

        try {
            // Build the same query as CalculateRatings but filtered by physics/mode
            $query = DB::table('records')
                ->whereNull('deleted_at')
                ->where('physics', $physics)
                ->where('mode', $mode)
                ->select('name', 'mdd_id', 'user_id', 'mapname', 'physics', 'mode', 'time', 'date_set');

            $this->info('Step 1: Adding ranks...');
            $query = $this->addRanks($query);

            $this->info('Step 2: Adding map participators...');
            $query = $this->addMapTotalParticipators($query);

            $this->info('Step 3: Adding top times...');
            $query = $this->addTopTimes($query);

            $this->info('Step 4: Adding relative times...');
            $query = $this->addReltimes($query);

            $this->info('Step 5: Adding banned maps...');
            $query = $this->addBannedMaps($query);

            $this->info('Step 6: Calculating map scores...');
            $query = $this->addMapScores($query);

            $this->info('Step 7: Adding weighted scores...');
            $query = $this->addWeightedMapScores($query);

            $this->info('Step 8: Adding player record counts...');
            $query = $this->addPlayerRecordsInCategory($query);

            $this->info('Step 9: Adding last activity...');
            $query = $this->addLastActivity($query);

            $this->info('Step 10: Computing player ratings...');
            $query = $this->computePlayerRatings($query);

            $this->info('Step 11: Adding total participators...');
            $query = $this->addCategoryTotalParticipators($query);

            $this->info('Step 12: Adding ranks...');
            $query = $this->addAllPlayersRank($query);
            $query = $this->addActivePlayersRank($query);

            $this->info('Step 13: Selecting final columns...');
            $query = $this->selectFinalColumns($query);

            $this->info('Step 14: Fetching results...');
            $result = $query->get();

            $this->info('Step 15: Updating database (' . count($result) . ' ratings)...');

            foreach ($result as $row) {
                $existingRating = PlayerRating::where('mdd_id', $row->mdd_id)
                    ->where('physics', $row->physics)
                    ->where('mode', $row->mode)
                    ->first();

                if ($existingRating) {
                    $existingRating->delete();
                }

                PlayerRating::create([
                    'name' => $row->name,
                    'mdd_id' => $row->mdd_id,
                    'user_id' => $row->user_id,
                    'physics' => $row->physics,
                    'mode' => $row->mode,
                    'all_players_rank' => $row->all_players_rank,
                    'active_players_rank' => $row->active_players_rank,
                    'category_total_participators' => $row->category_total_participators,
                    'player_records_in_category' => $row->player_records_in_category,
                    'last_activity' => $row->last_activity,
                    'player_rating' => $row->player_rating,
                ]);
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("✓ Completed in {$duration}s");
            Log::info("Calculated ratings", ['physics' => $physics, 'mode' => $mode, 'duration' => $duration, 'count' => count($result)]);

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed: " . $e->getMessage());
            Log::error("Failed to calculate ratings", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }
    }

    // Copy all the helper methods from CalculateRatings.php
    protected function addRanks($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('DENSE_RANK() OVER (PARTITION BY mapname, physics, mode ORDER BY time) AS record_map_rank'));
    }

    protected function addMapTotalParticipators($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('COUNT(*) OVER (PARTITION BY mapname, physics, mode) AS map_total_participators'));
    }

    protected function addTopTimes($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect([
                DB::raw('COALESCE(NULLIF(MAX(CASE WHEN record_map_rank = 1 THEN time END) OVER (PARTITION BY mapname, physics, mode), 0), 1) AS top1_time'),
                DB::raw('COALESCE(MAX(CASE WHEN record_map_rank = 2 THEN time END) OVER (PARTITION BY mapname, physics, mode), COALESCE(NULLIF(MAX(CASE WHEN record_map_rank = 1 THEN time END) OVER (PARTITION BY mapname, physics, mode), 0), 1)) AS top2_time')
            ]);
    }

    protected function addReltimes($query)
    {
        $query = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('CASE WHEN record_map_rank != 1 THEN time / top1_time ELSE time / top2_time END AS reltime'));

        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('MIN(reltime) OVER (PARTITION BY mapname, physics, mode) AS top_reltime'));
    }

    protected function addBannedMaps($query)
    {
        $bannedMaps = "'" . implode("','", self::BANNED_MAPS) . "'";
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('CASE WHEN map_total_participators < ' . self::MIN_MAP_TOTAL_PARTICIPATORS . ' OR top1_time < ' . self::MIN_TOP1_TIME . ' OR top_reltime < ' . self::MIN_TOP_RELTIME . ' OR mapname IN (' . $bannedMaps . ') THEN true ELSE false END AS map_banned'));
    }

    protected function addMapScores($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('CASE WHEN map_banned = False THEN 1000 * (' . self::CFG_A . ' + (-' . self::CFG_A . ' / POWER(1 + ' . self::CFG_Q . ' * EXP( - ' . self::CFG_B . ' * (reltime - ' . self::CFG_M . ')), 1 / ' . self::CFG_V . '))) ELSE 0 END AS map_score'));
    }

    protected function addWeightedMapScores($query)
    {
        $query = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('DENSE_RANK() OVER (PARTITION BY mdd_id, physics, mode ORDER BY map_score DESC) AS record_player_rank'));

        $query = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('EXP(-' . self::CFG_D . ' * record_player_rank) AS weight'));

        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('map_score * weight AS weighted_map_score'));
    }

    protected function addPlayerRecordsInCategory($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('COUNT(*) OVER (PARTITION BY mdd_id, physics, mode) AS player_records_in_category'));
    }

    protected function addLastActivity($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('MAX(date_set) OVER (PARTITION BY mdd_id, physics, mode) AS last_activity'));
    }

    protected function computePlayerRatings($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('CASE WHEN COUNT(time) < ' . self::MIN_TOTAL_RECORDS . ' THEN (SUM(weighted_map_score) / SUM(weight)) * COUNT(time) / ' . self::MIN_TOTAL_RECORDS . ' ELSE SUM(weighted_map_score) / SUM(weight) END AS player_rating'))
            ->groupBy('mdd_id', 'physics', 'mode');
    }

    protected function addCategoryTotalParticipators($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('COUNT(*) OVER (PARTITION BY physics, mode) AS category_total_participators'));
    }

    protected function addAllPlayersRank($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('DENSE_RANK() OVER (PARTITION BY physics, mode ORDER BY player_rating DESC) AS all_players_rank'));
    }

    protected function addActivePlayersRank($query)
    {
        $threeMonthsAgo = now()->subMonths(self::ACTIVE_PLAYERS_MONTHS);
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw("DENSE_RANK() OVER (PARTITION BY physics, mode ORDER BY CASE WHEN last_activity >= '{$threeMonthsAgo}' THEN player_rating ELSE 0 END DESC) AS active_players_rank"));
    }

    protected function selectFinalColumns($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->select(['name', 'mdd_id', 'user_id', 'physics', 'mode', 'all_players_rank', 'active_players_rank', 'category_total_participators', 'player_records_in_category', 'last_activity', 'player_rating']);
    }
}
