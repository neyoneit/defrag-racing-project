<?php

namespace App\Console\Commands;

use App\Services\CommunityScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CalculateCommunityScores extends Command
{
    protected $signature = 'community:calculate-scores';

    protected $description = 'Calculate community helper scores for all users';

    public function handle(CommunityScoreService $service): int
    {
        $this->info('Calculating community scores...');

        $service->calculateAll();

        // Clear all paginated leaderboard cache pages
        $page = 1;
        while (Cache::has("community:leaderboard:page:{$page}")) {
            Cache::forget("community:leaderboard:page:{$page}");
            $page++;
        }
        // Always clear at least first 5 pages in case cache check is unreliable
        for ($i = 1; $i <= max(5, $page); $i++) {
            Cache::forget("community:leaderboard:page:{$i}");
        }

        $this->info('Community scores calculated successfully.');

        return self::SUCCESS;
    }
}
