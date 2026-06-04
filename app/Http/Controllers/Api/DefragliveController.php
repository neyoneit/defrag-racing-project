<?php

namespace App\Http\Controllers\Api;

use App\Events\DefragliveStreamEvent;
use App\Http\Controllers\Controller;
use App\Models\DefragliveServerState;
use App\Models\DefragliveSetting;
use App\Services\DefragliveSettingsService;
use Illuminate\Http\Request;

/**
 * Command / relay surface for the web-native DefragLive (replaces the bridge's
 * inbound handling of settings_batch / get_current_settings / command). The bot
 * subscribes to the `defraglive` Reverb channel and acts on `execute_command`
 * events broadcast here - same single-channel model the bridge used.
 *
 * Auth model mirrors the existing (deliberately lightweight) design: commands
 * that drive the bot require the current bot_secret (which the extension
 * already holds from serverstate, see PlayerList.js). Reading settings is open.
 * Real per-viewer auth is a later hardening tied to the new extension.
 */
class DefragliveController extends Controller
{
    public function __construct(private DefragliveSettingsService $settings)
    {
    }

    /** get_current_settings parity - the extension reads the current game settings. */
    public function getSettings()
    {
        return response()->json(['settings' => DefragliveSetting::current()]);
    }

    /** settings_batch parity - the extension changes graphics; convert to cvars and drive the bot. */
    public function applySettings(Request $request)
    {
        if (!$this->validBotSecret($request)) {
            return response()->json(['error' => 'Invalid bot secret'], 403);
        }

        $settings = $request->input('settings', []);
        if (!is_array($settings) || empty($settings)) {
            return response()->json(['error' => 'No settings'], 422);
        }

        DefragliveSetting::store($settings);

        // Drive the bot: one execute_command per cvar command (+ vid_restart).
        foreach ($this->settings->convertToCommands($settings) as $command) {
            DefragliveStreamEvent::dispatchLive([
                'action' => 'execute_command',
                'command' => $command,
            ]);
        }

        // Update every viewer's UI.
        DefragliveStreamEvent::dispatchLive([
            'action' => 'settings_applied',
            'settings' => $settings,
            'username' => $request->input('username', 'Unknown User'),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /** Generic bot command (e.g. spectate a player from the player list). bot_secret gated. */
    public function command(Request $request)
    {
        if (!$this->validBotSecret($request)) {
            return response()->json(['error' => 'Invalid bot secret'], 403);
        }

        $command = $request->input('command');
        if (!is_string($command) || $command === '') {
            return response()->json(['error' => 'No command'], 422);
        }

        DefragliveStreamEvent::dispatchLive([
            'action' => 'execute_command',
            'command' => $command,
        ]);

        return response()->json(['status' => 'ok']);
    }

    /** sync_settings parity - the bot pushes the live game state in (token-guarded route). */
    public function syncSettings(Request $request)
    {
        $settings = $request->input('settings', []);
        if (!is_array($settings)) {
            return response()->json(['error' => 'No settings'], 422);
        }

        DefragliveSetting::store($settings);

        DefragliveStreamEvent::dispatchLive([
            'action' => 'current_settings',
            'settings' => DefragliveSetting::current(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /** The request's bot_secret must match the bot's current serverstate secret. */
    private function validBotSecret(Request $request): bool
    {
        $provided = (string) $request->input('bot_secret', '');
        if ($provided === '') {
            return false;
        }

        $state = DefragliveServerState::find(1)?->payload ?? [];
        $current = (string) ($state['bot_secret'] ?? '');

        return $current !== '' && hash_equals($current, $provided);
    }
}
