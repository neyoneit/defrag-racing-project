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

        Cache::forget('community:leaderboard');

        $this->info('Community scores calculated successfully.');

        return self::SUCCESS;
    }
}
