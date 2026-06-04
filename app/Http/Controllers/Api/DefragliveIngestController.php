<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DefragliveChatMessage;
use App\Models\DefragliveServerState;
use Illuminate\Http\Request;

/**
 * Ingest endpoint the DefragLive WebSocket bridge POSTs every persisted
 * broadcast to. Token-guarded server-to-server (DefragliveTokenMiddleware).
 *
 * The bridge keeps doing its realtime WS fan-out to the extension exactly as
 * before; this is purely the persistence tap that makes the web the source of
 * truth. We store the SAME set of actions the bridge wrote to console.json,
 * and the full payload, so DefragliveJsonWriter can rebuild the public
 * console.json/serverstate.json the legacy extension reads.
 */
class DefragliveIngestController extends Controller
{
    // Actions the bridge persisted to console.json (its save_message() set).
    // Kept verbatim so the rebuilt console.json matches what the extension
    // expects today.
    private const CHAT_ACTIONS = [
        'message',
        'command',
        'ext_command',
        'afk_notification',
        'afk_help',
        'server_record_celebration',
    ];

    public function ingest(Request $request)
    {
        $data = $request->all();
        $action = $data['action'] ?? null;

        if (!$action || !is_string($action)) {
            return response()->json(['error' => 'missing action'], 422);
        }

        if ($action === 'serverstate') {
            // Single evolving snapshot - the bridge stored data['message'].
            DefragliveServerState::query()->updateOrCreate(
                ['id' => 1],
                ['payload' => $data['message'] ?? []]
            );

            return response()->json(['status' => 'ok']);
        }

        if (in_array($action, self::CHAT_ACTIONS, true)) {
            $message = is_array($data['message'] ?? null) ? $data['message'] : [];

            DefragliveChatMessage::create([
                'message_id'    => $message['id'] ?? null,
                'action'        => $action,
                'author'        => $message['author'] ?? null,
                'content'       => $message['content'] ?? null,
                'msg_timestamp' => $message['timestamp'] ?? null,
                'payload'       => $data,
            ]);

            return response()->json(['status' => 'ok']);
        }

        // translation_result / settings_applied / current_settings / connect_*
        // are realtime-only and not persisted - the live WS handles them.
        return response()->json(['status' => 'ignored']);
    }
}
