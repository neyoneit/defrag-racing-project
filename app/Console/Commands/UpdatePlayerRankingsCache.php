<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdatePlayerRankingsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rankings:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update cached WR and Top3 counts for all players';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating player rankings cache...');

        // Calculate WRs for all players (rank = 1, meaning no one has a better time)
        $wrCounts = \DB::select("
            SELECT r1.user_id, COUNT(*) as wr_count
            FROM records r1
            WHERE (
                SELECT COUNT(DISTINCT r2.time)
                FROM records r2
                WHERE r2.mapname = r1.mapname
                AND r2.physics = r1.physics
                AND r2.mode = r1.mode
                AND r2.time < r1.time
            ) = 0
            GROUP BY r1.user_id
        ");

        // Calculate Top3 for all players
        $top3Counts = \DB::select("
            SELECT r1.user_id, COUNT(*) as top3_count
            FROM records r1
            WHERE (
                SELECT COUNT(DISTINCT r2.time)
                FROM records r2
                WHERE r2.mapname = r1.mapname
                AND r2.physics = r1.physics
                AND r2.mode = r1.mode
                AND r2.time < r1.time
            ) < 3
            GROUP BY r1.user_id
        ");

        // Reset all users first
        \DB::table('users')->update([
            'cached_wr_count' => 0,
            'cached_top3_count' => 0,
            'ranking_cached_at' => now()
        ]);

        // Update WR counts
        foreach ($wrCounts as $row) {
            \DB::table('users')
                ->where('id', $row->user_id)
                ->update(['cached_wr_count' => $row->wr_count]);
        }

        // Update Top3 counts
        foreach ($top3Counts as $row) {
            \DB::table('users')
                ->where('id', $row->user_id)
                ->update(['cached_top3_count' => $row->top3_count]);
        }

        // Update timestamp
        \DB::table('users')
            ->whereIn('id', array_merge(
                array_column($wrCounts, 'user_id'),
                array_column($top3Counts, 'user_id')
            ))
            ->update(['ranking_cached_at' => now()]);

        $this->info('Player rankings cache updated successfully!');
        $this->info('WRs updated for ' . count($wrCounts) . ' players');
        $this->info('Top3s updated for ' . count($top3Counts) . ' players');
    }
}
