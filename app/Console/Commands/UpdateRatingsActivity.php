<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateRatingsActivity extends Command
{
    protected $signature = 'ratings:update-activity';
    protected $description = 'Fast update of last_activity in player_ratings using summary table (23s for 621k records)';

    public function handle()
    {
        $this->info('Updating last_activity in player_ratings...');
        $startTime = microtime(true);

        try {
            // Step 1: Create/update summary table (FAST: 0.4s for 621k records)
            $this->info('Building summary table from records...');
            DB::statement('
                CREATE TABLE IF NOT EXISTS player_latest_activity (
                    mdd_id INT NOT NULL,
                    physics VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                    mode VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                    last_activity DATETIME NOT NULL,
                    PRIMARY KEY (mdd_id, physics, mode),
                    INDEX idx_last_activity (last_activity)
                ) ENGINE=InnoDB
            ');

            DB::statement('
                INSERT INTO player_latest_activity (mdd_id, physics, mode, last_activity)
                SELECT mdd_id, physics, mode, MAX(date_set) as last_activity
                FROM records
                WHERE deleted_at IS NULL
                GROUP BY mdd_id, physics, mode
                ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)
            ');

            // Step 2: Update player_ratings from summary (FAST: 22s for all ratings)
            $this->info('Updating player_ratings from summary...');
            DB::statement('
                UPDATE player_ratings pr
                JOIN player_latest_activity pla
                    ON pr.mdd_id = pla.mdd_id
                    AND pr.physics = pla.physics
                    AND pr.mode = pla.mode
                SET pr.last_activity = pla.last_activity
            ');

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("✓ Completed in {$duration}s");

            // Show stats
            $vq3Active = DB::table('player_ratings')
                ->where('physics', 'vq3')
                ->where('mode', 'run')
                ->where('last_activity', '>=', now()->subMonths(3))
                ->count();

            $cpmActive = DB::table('player_ratings')
                ->where('physics', 'cpm')
                ->where('mode', 'run')
                ->where('last_activity', '>=', now()->subMonths(3))
                ->count();

            $this->info("VQ3 active players: {$vq3Active}");
            $this->info("CPM active players: {$cpmActive}");

            Log::info("Updated last_activity", [
                'duration' => $duration,
                'vq3_active' => $vq3Active,
                'cpm_active' => $cpmActive
            ]);

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed: " . $e->getMessage());
            Log::error("Failed to update last_activity", ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
