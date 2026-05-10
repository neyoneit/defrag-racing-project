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
     */
    public function index(Request $request)
    {
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
