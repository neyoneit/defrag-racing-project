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
}
