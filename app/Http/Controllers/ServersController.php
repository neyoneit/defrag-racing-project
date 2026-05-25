<?php

namespace App\Http\Controllers;

use App\Services\ServerListService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServersController extends Controller
{
    public function index(Request $request)
    {
        // Render the page immediately with an empty list; the Vue page
        // then fetches the live data via /api/servers/live so a slow
        // scrape query doesn't gate the initial paint.
        return Inertia::render('Servers')->with('servers', []);
    }

    public function apiServers(Request $request, ServerListService $servers)
    {
        return response()->json($servers->list($request));
    }
}
