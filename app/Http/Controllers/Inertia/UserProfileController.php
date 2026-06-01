<?php

namespace App\Http\Controllers\Inertia;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController as Base;

/**
 * Local override of Jetstream's UserProfileController. Only purpose:
 * augment each session row with an opaque `handle` (sha256 of the
 * session id) so the frontend can pass it back to
 * BrowserSessionsController::destroy without exposing the real id.
 *
 * Everything else delegates to the parent.
 */
class UserProfileController extends Base
{
    public function sessions(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        $base = parent::sessions($request);

        $idMap = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->pluck('id')
            ->values();

        return $base->values()->map(function ($row, $i) use ($idMap) {
            $sid = $idMap[$i] ?? null;
            $row->handle = $sid ? hash('sha256', $sid) : null;
            return $row;
        });
    }
}
