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

    /**
     * Runs every two minutes, and what it writes is read by the server list -
     * a player streaming right now gets a dot beside their name there.
     *
     * It used to ask Twitch once per user, with that user's own access token,
     * which meant it only ever covered the handful of people who had been
     * through the full Twitch sign-in. Everyone who simply typed their channel
     * into their settings - four out of five of them - was invisible, and on a
     * feature whose whole point is to spot streamers in the overview, four out
     * of five is nothing.
     *
     * Now it asks once per hundred channels with the app token. Tokens are
     * still refreshed for the accounts that have one, because the rest of the
     * Twitch integration needs them, but the live check no longer depends on
     * any of it.
     */
    public function handle()
    {
        $this->refreshExpiringTokens();

        $users = User::whereNotNull('twitch_name')->get(['id', 'name', 'twitch_name', 'is_live']);

        // A login somebody typed can be unusable ("my channel", an empty
        // string). Those keep whatever they had rather than being reported as
        // offline on the strength of a question never asked.
        $loginByUser = [];

        foreach ($users as $user) {
            $login = TwitchService::loginFromName($user->twitch_name);

            if ($login !== null) {
                $loginByUser[$user->id] = $login;
            }
        }

        if (empty($loginByUser)) {
            $this->info('No usable Twitch channel names on file.');

            return 0;
        }

        $this->info('Checking ' . count($loginByUser) . ' channel(s)...');

        $live = array_flip($this->twitchService->liveLogins(array_values($loginByUser)));

        $nowLive = [];
        $wentOffline = [];

        foreach ($users as $user) {
            if (!isset($loginByUser[$user->id])) {
                continue;
            }

            $isLive = isset($live[$loginByUser[$user->id]]);

            // Written every pass regardless, so live_status_checked_at says
            // when we last actually knew, not when the flag last flipped.
            $user->forceFill([
                'is_live' => $isLive,
                'live_status_checked_at' => Carbon::now(),
            ])->saveQuietly();

            if ($isLive) {
                $nowLive[] = $user->name;
            } elseif ($user->getOriginal('is_live')) {
                $wentOffline[] = $user->name;
            }
        }

        $this->info('Live: ' . (empty($nowLive) ? 'nobody' : implode(', ', $nowLive)));

        if (!empty($wentOffline)) {
            $this->line('Went offline: ' . implode(', ', $wentOffline));
        }

        return 0;
    }

    /**
     * Keeps the stored user tokens usable for everything else that needs one.
     * Nothing here decides the live flag any more.
     */
    private function refreshExpiringTokens(): void
    {
        $users = User::whereNotNull('twitch_refresh_token')
            ->whereNotNull('twitch_token_expires_at')
            ->where('twitch_token_expires_at', '<=', Carbon::now())
            ->get();

        foreach ($users as $user) {
            $tokens = $this->twitchService->refreshAccessToken($user->twitch_refresh_token);

            if (!$tokens) {
                continue;
            }

            $user->forceFill([
                'twitch_token' => $tokens['access_token'],
                'twitch_refresh_token' => $tokens['refresh_token'],
                'twitch_token_expires_at' => Carbon::now()->addSeconds($tokens['expires_in']),
            ])->saveQuietly();
        }
    }
}
