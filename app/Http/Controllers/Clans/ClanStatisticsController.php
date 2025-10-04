<?php

namespace App\Http\Controllers\Clans;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ClanStatisticsController extends Controller
{
    public function getStatistics(Clan $clan)
    {
        // Cache for 1 hour to reduce database load
        return Cache::remember("clan_stats_{$clan->id}", 3600, function () use ($clan) {
            $memberIds = $clan->players->pluck('user_id')->toArray();

            if (empty($memberIds)) {
                return $this->getEmptyStats();
            }

            return [
                'overview' => $this->getOverviewStats($memberIds),
                'hall_of_fame' => $this->getHallOfFame($memberIds),
                'leaderboards' => $this->getLeaderboards($memberIds),
                'special_sections' => $this->getSpecialSections($memberIds, $clan),
            ];
        });
    }

    private function getOverviewStats($memberIds)
    {
        $memberIdsStr = implode(',', array_map('intval', $memberIds));

        // Get all basic stats in a single query
        $stats = DB::select("
            SELECT
                COUNT(*) as total_records,
                COUNT(DISTINCT mapname) as unique_maps,
                SUM(CASE WHEN date_set >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_activity,
                COUNT(DISTINCT DATE(date_set)) as active_days
            FROM records
            WHERE user_id IN ($memberIdsStr)
        ")[0];

        // Fast top1 count using index
        $top1Count = DB::select("
            SELECT COUNT(*) as count
            FROM (
                SELECT r1.id
                FROM records r1
                INNER JOIN (
                    SELECT mapname, physics, mode, MIN(time) as best_time
                    FROM records
                    GROUP BY mapname, physics, mode
                ) r2 ON r1.mapname = r2.mapname
                    AND r1.physics = r2.physics
                    AND r1.mode = r2.mode
                    AND r1.time = r2.best_time
                WHERE r1.user_id IN ($memberIdsStr)
            ) as top1s
        ")[0]->count ?? 0;

        // Fast top3 count
        $top3Count = DB::select("
            SELECT COUNT(*) as count
            FROM (
                SELECT r1.id
                FROM records r1
                WHERE r1.user_id IN ($memberIdsStr)
                AND (
                    SELECT COUNT(DISTINCT r2.time)
                    FROM records r2
                    WHERE r2.mapname = r1.mapname
                    AND r2.physics = r1.physics
                    AND r2.mode = r1.mode
                    AND r2.time < r1.time
                ) < 3
            ) as top3s
        ")[0]->count ?? 0;

        // Optimized streak calculation using SQL
        $longestStreak = DB::select("
            WITH dates AS (
                SELECT DISTINCT DATE(date_set) as record_date
                FROM records
                WHERE user_id IN ($memberIdsStr)
                ORDER BY record_date
            ),
            streaks AS (
                SELECT
                    record_date,
                    record_date - INTERVAL ROW_NUMBER() OVER (ORDER BY record_date) DAY as streak_group
                FROM dates
            ),
            streak_lengths AS (
                SELECT
                    streak_group,
                    COUNT(*) as streak_length
                FROM streaks
                GROUP BY streak_group
            )
            SELECT COALESCE(MAX(streak_length), 0) as longest_streak
            FROM streak_lengths
        ")[0]->longest_streak ?? 0;

        // Most records in a single day
        $mostRecordsPerDay = DB::select("
            SELECT COUNT(*) as count
            FROM records
            WHERE user_id IN ($memberIdsStr)
            GROUP BY DATE(date_set)
            ORDER BY count DESC
            LIMIT 1
        ")[0]->count ?? 0;

        // Average WR age in days
        $avgWrAge = DB::select("
            SELECT AVG(DATEDIFF(NOW(), r1.date_set)) as avg_age
            FROM records r1
            INNER JOIN (
                SELECT mapname, physics, mode, MIN(time) as best_time
                FROM records
                GROUP BY mapname, physics, mode
            ) r2 ON r1.mapname = r2.mapname
                AND r1.physics = r2.physics
                AND r1.mode = r2.mode
                AND r1.time = r2.best_time
            WHERE r1.user_id IN ($memberIdsStr)
        ")[0]->avg_age ?? 0;

        // Physics preference (VQ3 vs CPM)
        $physicsStats = DB::select("
            SELECT
                SUM(CASE WHEN physics LIKE '%vq3%' THEN 1 ELSE 0 END) as vq3_count,
                SUM(CASE WHEN physics LIKE '%cpm%' THEN 1 ELSE 0 END) as cpm_count
            FROM records
            WHERE user_id IN ($memberIdsStr)
        ")[0];

        // Top 10 density
        $top10Count = DB::select("
            SELECT COUNT(*) as count
            FROM (
                SELECT r1.id
                FROM records r1
                WHERE r1.user_id IN ($memberIdsStr)
                AND (
                    SELECT COUNT(DISTINCT r2.time)
                    FROM records r2
                    WHERE r2.mapname = r1.mapname
                    AND r2.physics = r1.physics
                    AND r2.mode = r1.mode
                    AND r2.time < r1.time
                ) < 10
            ) as top10s
        ")[0]->count ?? 0;

        // Completion rate
        $completionRate = DB::select("
            SELECT
                (COUNT(DISTINCT r.mapname) * 100.0 / NULLIF((SELECT COUNT(DISTINCT mapname) FROM records), 0)) as rate
            FROM records r
            WHERE r.user_id IN ($memberIdsStr)
        ")[0]->rate ?? 0;

        return [
            'total_records' => $stats->total_records ?? 0,
            'top1_count' => $top1Count,
            'top3_count' => $top3Count,
            'top10_count' => $top10Count,
            'longest_streak' => $longestStreak,
            'most_records_per_day' => $mostRecordsPerDay,
            'recent_activity' => $stats->recent_activity ?? 0,
            'unique_maps' => $stats->unique_maps ?? 0,
            'avg_wr_age_days' => round($avgWrAge, 1),
            'vq3_count' => $physicsStats->vq3_count ?? 0,
            'cpm_count' => $physicsStats->cpm_count ?? 0,
            'completion_rate' => round($completionRate, 1),
        ];
    }

    private function getHallOfFame($memberIds)
    {
        $memberIdsStr = implode(',', array_map('intval', $memberIds));

        // Record King - Most total records
        $recordKing = DB::select("
            SELECT user_id, COUNT(*) as total
            FROM records
            WHERE user_id IN ($memberIdsStr)
            GROUP BY user_id
            ORDER BY total DESC
            LIMIT 1
        ")[0] ?? null;

        // Speed Demon - Most #1 positions
        $speedDemon = DB::select("
            SELECT r1.user_id, COUNT(*) as top1_count
            FROM records r1
            INNER JOIN (
                SELECT mapname, physics, mode, MIN(time) as best_time
                FROM records
                GROUP BY mapname, physics, mode
            ) r2 ON r1.mapname = r2.mapname
                AND r1.physics = r2.physics
                AND r1.mode = r2.mode
                AND r1.time = r2.best_time
            WHERE r1.user_id IN ($memberIdsStr)
            GROUP BY r1.user_id
            ORDER BY top1_count DESC
            LIMIT 1
        ")[0] ?? null;

        // Hot Streak - Longest consecutive days (optimized per player)
        $hotStreak = DB::select("
            WITH player_dates AS (
                SELECT
                    user_id,
                    DATE(date_set) as record_date
                FROM records
                WHERE user_id IN ($memberIdsStr)
                GROUP BY user_id, DATE(date_set)
            ),
            player_streaks AS (
                SELECT
                    user_id,
                    record_date,
                    record_date - INTERVAL ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY record_date) DAY as streak_group
                FROM player_dates
            ),
            player_streak_lengths AS (
                SELECT
                    user_id,
                    MAX(streak_count) as max_streak
                FROM (
                    SELECT
                        user_id,
                        streak_group,
                        COUNT(*) as streak_count
                    FROM player_streaks
                    GROUP BY user_id, streak_group
                ) as grouped_streaks
                GROUP BY user_id
            )
            SELECT user_id, max_streak as streak
            FROM player_streak_lengths
            ORDER BY max_streak DESC
            LIMIT 1
        ")[0] ?? null;

        // Sharpshooter - Most top 3 finishes
        $sharpshooter = DB::select("
            SELECT r1.user_id, COUNT(*) as top3_count
            FROM records r1
            WHERE r1.user_id IN ($memberIdsStr)
            AND (
                SELECT COUNT(DISTINCT r2.time)
                FROM records r2
                WHERE r2.mapname = r1.mapname
                AND r2.physics = r1.physics
                AND r2.mode = r1.mode
                AND r2.time < r1.time
            ) < 3
            GROUP BY r1.user_id
            ORDER BY top3_count DESC
            LIMIT 1
        ")[0] ?? null;

        // Most Active (last 30 days)
        $mostActive = DB::select("
            SELECT user_id, COUNT(*) as recent_count
            FROM records
            WHERE user_id IN ($memberIdsStr)
            AND date_set >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY user_id
            ORDER BY recent_count DESC
            LIMIT 1
        ")[0] ?? null;

        // Map Explorer - Most unique maps
        $mapExplorer = DB::select("
            SELECT user_id, COUNT(DISTINCT mapname) as map_count
            FROM records
            WHERE user_id IN ($memberIdsStr)
            GROUP BY user_id
            ORDER BY map_count DESC
            LIMIT 1
        ")[0] ?? null;

        return [
            'record_king' => $this->enrichWithUserData($recordKing, 'total'),
            'speed_demon' => $this->enrichWithUserData($speedDemon, 'top1_count'),
            'hot_streak' => $this->enrichWithUserData($hotStreak, 'streak'),
            'sharpshooter' => $this->enrichWithUserData($sharpshooter, 'top3_count'),
            'most_active' => $this->enrichWithUserData($mostActive, 'recent_count'),
            'map_explorer' => $this->enrichWithUserData($mapExplorer, 'map_count'),
        ];
    }

    private function getLeaderboards($memberIds)
    {
        $memberIdsStr = implode(',', array_map('intval', $memberIds));

        // Total Records Leaderboard
        $totalRecordsLeaderboard = DB::select("
            SELECT
                u.id,
                u.name,
                u.plain_name,
                u.profile_photo_path,
                u.country,
                COUNT(*) as total
            FROM records r
            JOIN users u ON r.user_id = u.id
            WHERE u.id IN ($memberIdsStr)
            GROUP BY u.id, u.name, u.plain_name, u.profile_photo_path, u.country
            ORDER BY total DESC
        ");

        // Top Positions Leaderboard (optimized with subquery)
        $topPositionsLeaderboard = DB::select("
            SELECT
                u.id,
                u.name,
                u.plain_name,
                u.profile_photo_path,
                u.country,
                COALESCE(top1_counts.top1, 0) as top1,
                COALESCE(top3_counts.top3, 0) as top3
            FROM users u
            LEFT JOIN (
                SELECT r1.user_id, COUNT(*) as top1
                FROM records r1
                INNER JOIN (
                    SELECT mapname, physics, mode, MIN(time) as best_time
                    FROM records
                    GROUP BY mapname, physics, mode
                ) r2 ON r1.mapname = r2.mapname
                    AND r1.physics = r2.physics
                    AND r1.mode = r2.mode
                    AND r1.time = r2.best_time
                WHERE r1.user_id IN ($memberIdsStr)
                GROUP BY r1.user_id
            ) top1_counts ON u.id = top1_counts.user_id
            LEFT JOIN (
                SELECT r1.user_id, COUNT(*) as top3
                FROM records r1
                WHERE r1.user_id IN ($memberIdsStr)
                AND (
                    SELECT COUNT(DISTINCT r2.time)
                    FROM records r2
                    WHERE r2.mapname = r1.mapname
                    AND r2.physics = r1.physics
                    AND r2.mode = r1.mode
                    AND r2.time < r1.time
                ) < 3
                GROUP BY r1.user_id
            ) top3_counts ON u.id = top3_counts.user_id
            WHERE u.id IN ($memberIdsStr)
            ORDER BY top1 DESC, top3 DESC
        ");

        // Activity Leaderboard (Last 30 days)
        $activityLeaderboard = DB::select("
            SELECT
                u.id,
                u.name,
                u.plain_name,
                u.profile_photo_path,
                u.country,
                COUNT(*) as recent_count
            FROM records r
            JOIN users u ON r.user_id = u.id
            WHERE u.id IN ($memberIdsStr)
            AND r.date_set >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.id, u.name, u.plain_name, u.profile_photo_path, u.country
            ORDER BY recent_count DESC
        ");

        // Map Diversity Leaderboard
        $mapDiversityLeaderboard = DB::select("
            SELECT
                u.id,
                u.name,
                u.plain_name,
                u.profile_photo_path,
                u.country,
                COUNT(DISTINCT r.mapname) as unique_maps
            FROM records r
            JOIN users u ON r.user_id = u.id
            WHERE u.id IN ($memberIdsStr)
            GROUP BY u.id, u.name, u.plain_name, u.profile_photo_path, u.country
            ORDER BY unique_maps DESC
        ");

        return [
            'total_records' => $totalRecordsLeaderboard,
            'top_positions' => $topPositionsLeaderboard,
            'activity' => $activityLeaderboard,
            'map_diversity' => $mapDiversityLeaderboard,
        ];
    }

    private function enrichWithUserData($record, $valueKey)
    {
        if (!$record) {
            return null;
        }

        $userId = is_object($record) ? $record->user_id : null;

        if (!$userId) {
            return null;
        }

        $user = DB::table('users')
            ->select('id', 'name', 'plain_name', 'profile_photo_path', 'country')
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return null;
        }

        return [
            'user' => $user,
            'value' => is_object($record) ? ($record->$valueKey ?? 0) : 0,
        ];
    }

    private function getSpecialSections($memberIds, $clan)
    {
        $memberIdsStr = implode(',', array_map('intval', $memberIds));

        // Rival Clans - Using cached WR and Top3 counts (FAST!)
        $rivalClans = DB::select("
            SELECT
                c.id,
                c.name,
                c.tag,
                c.image,
                COALESCE(SUM(u.cached_wr_count), 0) as total_wrs,
                COALESCE(SUM(u.cached_top3_count), 0) as total_top3,
                (COALESCE(SUM(u.cached_wr_count), 0) * 3 + COALESCE(SUM(u.cached_top3_count), 0)) as rivalry_score
            FROM clans c
            JOIN clan_players cp ON c.id = cp.clan_id
            JOIN users u ON cp.user_id = u.id
            WHERE c.id != ?
            GROUP BY c.id, c.name, c.tag, c.image
            HAVING rivalry_score > 0
            ORDER BY rivalry_score DESC
            LIMIT 5
        ", [$clan->id]);

        // Podium Sweeps - Simplified without window functions
        $podiumSweeps = [];

        // Untouchable World Records - Only current #1 times held by clan members
        $untouchableRecords = DB::select("
            WITH current_wrs AS (
                SELECT
                    mapname,
                    physics,
                    mode,
                    MIN(time) as best_time
                FROM records
                GROUP BY mapname, physics, mode
            )
            SELECT
                r.id,
                r.mapname,
                r.physics,
                r.mode,
                r.time,
                r.date_set,
                DATEDIFF(NOW(), r.date_set) as days_held,
                u.id as user_id,
                u.name as user_name,
                u.plain_name,
                u.profile_photo_path
            FROM records r
            JOIN users u ON r.user_id = u.id
            INNER JOIN current_wrs cw ON r.mapname = cw.mapname
                AND r.physics = cw.physics
                AND r.mode = cw.mode
                AND r.time = cw.best_time
            WHERE r.user_id IN ($memberIdsStr)
            ORDER BY days_held DESC
            LIMIT 10
        ");

        // Contested Positions - Simplified without window functions
        $contestedPositions = [];

        return [
            'rival_clans' => $rivalClans,
            'podium_sweeps' => $podiumSweeps,
            'untouchable_records' => $untouchableRecords,
            'contested_positions' => $contestedPositions,
        ];
    }

    private function getEmptyStats()
    {
        return [
            'overview' => [
                'total_records' => 0,
                'top1_count' => 0,
                'top3_count' => 0,
                'top10_count' => 0,
                'longest_streak' => 0,
                'most_records_per_day' => 0,
                'recent_activity' => 0,
                'unique_maps' => 0,
                'avg_wr_age_days' => 0,
                'vq3_count' => 0,
                'cpm_count' => 0,
                'completion_rate' => 0,
            ],
            'hall_of_fame' => [
                'record_king' => null,
                'speed_demon' => null,
                'hot_streak' => null,
                'sharpshooter' => null,
                'most_active' => null,
                'map_explorer' => null,
            ],
            'leaderboards' => [
                'total_records' => [],
                'top_positions' => [],
                'activity' => [],
                'map_diversity' => [],
            ],
            'special_sections' => [
                'rival_clans' => [],
                'podium_sweeps' => [],
                'untouchable_records' => [],
                'contested_positions' => [],
            ],
        ];
    }
}
