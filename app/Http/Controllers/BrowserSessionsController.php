<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Per-row revoke for browser sessions. Jetstream ships with bulk
 * "logout other browser sessions" only; this adds individual
 * revocation so the user can sign out one device without touching the
 * others.
 *
 * Session IDs are sensitive (they are the cookie value), so they are
 * never exposed in HTML. The frontend receives a sha256(id) as an
 * opaque `handle`; on revoke the server iterates the user's sessions
 * and deletes the one whose hash matches.
 */
class BrowserSessionsController extends Controller
{
    public function destroy(Request $request, string $handle)
    {
        $user = Auth::user();
        $currentId = $request->session()->getId();

        $sessions = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->select('id')
            ->get();

        foreach ($sessions as $row) {
            if (hash('sha256', $row->id) !== $handle) {
                continue;
            }

            // Block self-revoke: the current device should sign out via
            // the normal logout flow so the session cookie gets cleared
            // client-side too.
            if ($row->id === $currentId) {
                return response()->json(['error' => 'cannot_revoke_current'], 422);
            }

            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('id', $row->id)
                ->delete();

            return response()->json(['ok' => true]);
        }

        return response()->json(['error' => 'not_found'], 404);
    }
}
