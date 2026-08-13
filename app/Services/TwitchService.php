<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TwitchService
{
    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
        $this->clientSecret = config('services.twitch.client_secret');
    }

    /**
     * Get app access token for Twitch API
     */
    protected function getAppAccessToken()
    {
        $response = Http::post('https://id.twitch.tv/oauth2/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        return null;
    }

    /**
     * Check if a user is currently live streaming
     *
     * @param string $userId Twitch user ID
     * @param string|null $userToken User's access token (optional, can use app token)
     * @return bool
     */
    public function isUserLive($userId, $userToken = null)
    {
        $token = $userToken ?? $this->getAppAccessToken();

        if (!$token) {
            return false;
        }

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])->get('https://api.twitch.tv/helix/streams', [
            'user_id' => $userId,
        ]);

        if ($response->successful()) {
            $data = $response->json('data');
            return !empty($data);
        }

        return false;
    }

    /**
     * Get stream details if user is live
     *
     * @param string $userId Twitch user ID
     * @param string|null $userToken User's access token (optional)
     * @return array|null
     */
    public function getStreamDetails($userId, $userToken = null)
    {
        $token = $userToken ?? $this->getAppAccessToken();

        if (!$token) {
            return null;
        }

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])->get('https://api.twitch.tv/helix/streams', [
            'user_id' => $userId,
        ]);

        if ($response->successful()) {
            $data = $response->json('data');
            return $data[0] ?? null;
        }

        return null;
    }

    /**
     * Refresh user's access token
     *
     * @param string $refreshToken
     * @return array|null
     */
    public function refreshAccessToken($refreshToken)
    {
        $response = Http::post('https://id.twitch.tv/oauth2/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if ($response->successful()) {
            return [
                'access_token' => $response->json('access_token'),
                'refresh_token' => $response->json('refresh_token'),
                'expires_in' => $response->json('expires_in'),
            ];
        }

        return null;
    }

    /**
     * The channel name out of whatever somebody typed into the settings box.
     *
     * It is a free text field, so it holds display names, logins with an @ in
     * front, and full addresses - one account on production has
     * "https://www.twitch.tv/mix_anik" in it. Twitch matches on the login,
     * which is always lower case, and a display name differs from its login
     * only by case for all but a handful of very old accounts.
     */
    public static function loginFromName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        // Anything that looks like an address: keep the last path segment.
        if (preg_match('~twitch\.tv/([^/?#\s]+)~i', $name, $m)) {
            $name = $m[1];
        }

        $name = strtolower(ltrim(trim($name, "/ \t\n\r"), '@'));

        // Logins are 4-25 of [a-z0-9_]. Anything else was not a channel name
        // and asking Twitch about it only earns a 400 for the whole batch.
        return preg_match('/^[a-z0-9_]{4,25}$/', $name) ? $name : null;
    }

    /**
     * Which of these channels are live right now.
     *
     * One request per hundred names instead of one per user: helix/streams
     * takes up to a hundred user_login parameters and answers only for the
     * ones that are streaming, so the reply is the answer with no further
     * filtering. Uses the app token, which means this works for everyone who
     * typed a channel name in their settings - not only for the few who went
     * through the full Twitch sign-in and left us a token of their own.
     *
     * @param  string[] $logins
     * @return string[] the logins that are live, lower case
     */
    public function liveLogins(array $logins): array
    {
        $logins = array_values(array_unique(array_filter($logins)));

        if (empty($logins)) {
            return [];
        }

        $token = $this->getAppAccessToken();

        if (!$token) {
            return [];
        }

        $live = [];

        foreach (array_chunk($logins, 100) as $chunk) {
            $response = Http::withHeaders([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://api.twitch.tv/helix/streams?' . http_build_query(['user_login' => $chunk]));

            if (!$response->successful()) {
                continue;
            }

            foreach ($response->json('data') ?? [] as $stream) {
                if (!empty($stream['user_login'])) {
                    $live[] = strtolower($stream['user_login']);
                }
            }
        }

        return $live;
    }
}
