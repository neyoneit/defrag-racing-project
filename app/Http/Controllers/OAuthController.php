<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OAuthController extends Controller
{
    /**
     * Redirect to Discord OAuth
     */
    public function redirectToDiscord()
    {
        return Socialite::driver('discord')
            ->scopes(['identify'])
            ->redirect();
    }

    /**
     * Handle Discord OAuth callback
     */
    public function handleDiscordCallback()
    {
        // Check if user denied authorization
        if (request()->has('error')) {
            return redirect()->route('profile.show')->with('info', 'Discord connection cancelled.');
        }

        try {
            $discordUser = Socialite::driver('discord')->user();

            $user = Auth::user();
            $user->update([
                'discord_name' => $discordUser->getNickname() ?? $discordUser->getName(),
                'discord_id' => $discordUser->getId(),
                'discord_token' => $discordUser->token,
                'discord_refresh_token' => $discordUser->refreshToken,
                'discord_token_expires_at' => Carbon::now()->addSeconds($discordUser->expiresIn),
            ]);

            return redirect()->route('profile.show')->with('success', 'Discord account connected successfully!');
        } catch (\Exception $e) {
            return redirect()->route('profile.show')->with('error', 'Failed to connect Discord account.');
        }
    }

    /**
     * Disconnect Discord
     */
    public function disconnectDiscord()
    {
        $user = Auth::user();
        $user->update([
            'discord_id' => null,
            'discord_token' => null,
            'discord_refresh_token' => null,
            'discord_token_expires_at' => null,
        ]);

        return redirect()->route('profile.show')->with('success', 'Discord account disconnected.');
    }

    /**
     * Redirect to Twitch OAuth
     */
    public function redirectToTwitch()
    {
        return Socialite::driver('twitch')
            ->scopes(['user:read:email'])
            ->redirect();
    }

    /**
     * Handle Twitch OAuth callback
     */
    public function handleTwitchCallback()
    {
        // Check if user denied authorization
        if (request()->has('error')) {
            return redirect()->route('profile.show')->with('info', 'Twitch connection cancelled.');
        }

        try {
            $twitchUser = Socialite::driver('twitch')->user();

            $user = Auth::user();
            $user->update([
                'twitch_name' => $twitchUser->getNickname() ?? $twitchUser->getName(),
                'twitch_id' => $twitchUser->getId(),
                'twitch_token' => $twitchUser->token,
                'twitch_refresh_token' => $twitchUser->refreshToken,
                'twitch_token_expires_at' => Carbon::now()->addSeconds($twitchUser->expiresIn),
            ]);

            return redirect()->route('profile.show')->with('success', 'Twitch account connected successfully!');
        } catch (\Exception $e) {
            return redirect()->route('profile.show')->with('error', 'Failed to connect Twitch account.');
        }
    }

    /**
     * Disconnect Twitch
     */
    public function disconnectTwitch()
    {
        $user = Auth::user();
        $user->update([
            'twitch_id' => null,
            'twitch_token' => null,
            'twitch_refresh_token' => null,
            'twitch_token_expires_at' => null,
            'is_live' => false,
            'live_status_checked_at' => null,
        ]);

        return redirect()->route('profile.show')->with('success', 'Twitch account disconnected.');
    }

    /**
     * Redirect to Steam OAuth
     */
    public function redirectToSteam()
    {
        return Socialite::driver('steam')->redirect();
    }

    /**
     * Handle Steam OAuth callback
     */
    public function handleSteamCallback()
    {
        // Check if user denied authorization
        if (request()->has('error')) {
            return redirect()->route('profile.show')->with('info', 'Steam connection cancelled.');
        }

        try {
            $steamUser = Socialite::driver('steam')->user();

            $user = Auth::user();
            $user->update([
                'steam_id' => $steamUser->getId(),
                'steam_name' => $steamUser->getNickname() ?? $steamUser->getName(),
                'steam_avatar' => $steamUser->getAvatar(),
            ]);

            return redirect()->route('profile.show')->with('success', 'Steam account connected successfully!');
        } catch (\Exception $e) {
            return redirect()->route('profile.show')->with('error', 'Failed to connect Steam account.');
        }
    }

    /**
     * Disconnect Steam
     */
    public function disconnectSteam()
    {
        $user = Auth::user();
        $user->update([
            'steam_id' => null,
            'steam_name' => null,
            'steam_avatar' => null,
        ]);

        return redirect()->route('profile.show')->with('success', 'Steam account disconnected.');
    }

    /**
     * Redirect to Twitter/X OAuth
     */
    public function redirectToTwitter()
    {
        return Socialite::driver('twitter')->redirect();
    }

    /**
     * Handle Twitter/X OAuth callback
     */
    public function handleTwitterCallback()
    {
        // Check if user denied authorization
        if (request()->has('error')) {
            return redirect()->route('profile.show')->with('info', 'Twitter/X connection cancelled.');
        }

        try {
            $twitterUser = Socialite::driver('twitter')->user();

            $user = Auth::user();
            $user->update([
                'twitter_name' => $twitterUser->getNickname() ?? $twitterUser->getName(),
                'twitter_id' => $twitterUser->getId(),
                'twitter_token' => $twitterUser->token,
                'twitter_refresh_token' => $twitterUser->refreshToken,
                'twitter_token_expires_at' => Carbon::now()->addSeconds($twitterUser->expiresIn),
            ]);

            return redirect()->route('profile.show')->with('success', 'Twitter/X account connected successfully!');
        } catch (\Exception $e) {
            return redirect()->route('profile.show')->with('error', 'Failed to connect Twitter/X account.');
        }
    }

    /**
     * Disconnect Twitter/X
     */
    public function disconnectTwitter()
    {
        $user = Auth::user();
        $user->update([
            'twitter_id' => null,
            'twitter_token' => null,
            'twitter_refresh_token' => null,
            'twitter_token_expires_at' => null,
        ]);

        return redirect()->route('profile.show')->with('success', 'Twitter/X account disconnected.');
    }
}
