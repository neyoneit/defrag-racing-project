<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\ServerListService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServersController extends Controller
{
    /** Enough for anyone who plays, low enough that a script cannot fill the table. */
    private const MAX_FAVORITES = 50;

    public function index(Request $request)
    {
        // Render the page immediately with an empty list; the Vue page
        // then fetches the live data via /api/servers/live so a slow
        // scrape query doesn't gate the initial paint.
        //
        // The starred ids ride along with the page instead of with the
        // live list: the list refreshes every 30 seconds, the star is
        // toggled without a reload, and a refresh that started before
        // the click landed would put the star back.
        return Inertia::render('Servers')
            ->with('servers', [])
            ->with('favoriteServers', $request->user()
                ? $request->user()->favoritedServers()->pluck('servers.id')
                : []);
    }

    public function favorite(Request $request, Server $server)
    {
        $user = $request->user();

        if ($user->favoritedServers()->count() >= self::MAX_FAVORITES
            && ! $user->favoritedServers()->where('servers.id', $server->id)->exists()) {
            return response()->json([
                'message' => 'You have starred the maximum of ' . self::MAX_FAVORITES . ' servers.',
            ], 422);
        }

        $user->favoritedServers()->syncWithoutDetaching([$server->id]);

        return response()->json(['favorited' => true]);
    }

    public function unfavorite(Request $request, Server $server)
    {
        $request->user()->favoritedServers()->detach($server->id);

        return response()->json(['favorited' => false]);
    }

    public function apiServers(Request $request, ServerListService $servers)
    {
        return response()->json($servers->list($request));
    }
}
