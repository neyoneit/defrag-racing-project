<?php

namespace App\Http\Controllers;

use App\Services\MapStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MapStatsController extends Controller
{
    public function __construct(private MapStatsService $stats) {}

    /**
     * Public stats dashboard — Plotly-rendered charts on the client.
     *
     * The full payload is ~14s to build cold and ~25ms warm. We don't
     * want the visitor's first request to block on the cold rebuild
     * (it would time out before HTML even ships), so:
     *
     *  - Initial full-page request: render the shell with `stats=null`
     *    and let the client paint a loading state immediately.
     *  - Inertia partial reload (X-Inertia-Partial-Data header set,
     *    asking for `stats`): actually compute / fetch the cached
     *    payload and return it.
     *
     * If the partial reload happens to land on a cold cache, the
     * client is the one that pays the wait — but it's an async XHR,
     * so the page is already interactive and the spinner stays
     * visible instead of the browser hanging on the navigation.
     */
    public function index(Request $request)
    {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        if (!$isPartial) {
            return Inertia::render('MapStats', ['stats' => null]);
        }

        return Inertia::render('MapStats', [
            'stats' => fn () => $this->stats->all(),
        ]);
    }

    /**
     * Raw JSON dump of the cached payload. NOT routed — bandwidth
     * amplification risk on a 4 MB response with no auth. Wire it up in
     * routes/web.php with throttle:30,60 if a public consumer needs it:
     *
     *   Route::get('/api/stats/maps.json', [MapStatsController::class, 'exportJson'])
     *       ->middleware('throttle:30,60')
     *       ->name('api.stats.maps');
     *
     * ETag + stale-while-revalidate let CDNs and notebook clients
     * short-circuit repeat reads with 304s.
     */
    public function exportJson(Request $request)
    {
        $payload     = $this->stats->all();
        $generatedAt = $payload['generated_at'] ?? now()->toIso8601String();
        $etag        = '"' . md5($generatedAt) . '"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=21600, stale-while-revalidate=86400');
        }

        return response()->json($payload)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=21600, stale-while-revalidate=86400')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
