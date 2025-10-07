<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\TwitchService;
use Carbon\Carbon;

class CheckTwitchLiveStatus extends Command
{
    protected $signature = 'twitch:check-live-status';
    protected $description = 'Check Twitch live status for all connected users';

    protected $twitchService;

    public function __construct(TwitchService $twitchService)
    {
        parent::__construct();
        $this->twitchService = $twitchService;
    }

    public function handle()
    {
        $users = User::whereNotNull('twitch_id')->get();

        $this->info("Checking live status for {$users->count()} users...");

        foreach ($users as $user) {
            // Check if we need to refresh the token
            if ($user->twitch_token_expires_at && Carbon::parse($user->twitch_token_expires_at)->isPast()) {
                $this->info("Refreshing token for {$user->username}...");
                $newTokens = $this->twitchService->refreshAccessToken($user->twitch_refresh_token);

                if ($newTokens) {
                    $user->update([
                        'twitch_token' => $newTokens['access_token'],
                        'twitch_refresh_token' => $newTokens['refresh_token'],
                        'twitch_token_expires_at' => Carbon::now()->addSeconds($newTokens['expires_in']),
                    ]);
                }
            }

            $isLive = $this->twitchService->isUserLive($user->twitch_id, $user->twitch_token);

            $user->update([
                'is_live' => $isLive,
                'live_status_checked_at' => Carbon::now(),
            ]);

            if ($isLive) {
                $this->info("✓ {$user->username} is LIVE");
            }
        }

        $this->info('Done!');
        return 0;
    }
}
