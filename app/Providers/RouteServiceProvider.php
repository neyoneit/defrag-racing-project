<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Launcher API buckets, keyed per token so two devices on the
        // same account don't share quota. Three buckets, one per
        // workload shape, so heavy traffic in one can't starve another:
        //
        //  - launcher-lookup: hash-by-lookup ONLY. Rescans burn this
        //    bucket - one call per cache-miss demo. A power user on a
        //    fresh install with thousands of demos and the Faster (no
        //    limit) CPU setting can fire 50-100 requests/sec for a
        //    couple of minutes. 6000/min = 100/sec covers that without
        //    429 and sits well under any abuse threshold (single
        //    indexed read).
        //
        //  - launcher-browse: servers, notifications, records, maps,
        //    me. User-initiated UI calls; polling intervals are ~60s,
        //    a click can't fire faster than a few per second. 600/min
        //    = 10/sec is huge headroom for that traffic pattern and
        //    crucially is RESERVED from the lookup budget so a heavy
        //    rescan can't 429 the server browser / Records / Maps
        //    while it drains. Previously these shared launcher-read
        //    with lookup-by-hash and a Faster-button rescan could
        //    starve them.
        //
        //  - launcher-upload: upload-demo. Actual work (multipart
        //    receive + ProcessDemoJob dispatch); 7 workers at ~1-2s
        //    per job = ~210-420 jobs/min sustained capacity. 300/min
        //    (5/sec) absorbs first-run bursts and queues overflow at
        //    worker rate via the DB queue table.
        //
        // Falls back to user id or IP when somehow no token is present
        // (shouldn't happen on the launcher routes, belt & braces).
        $launcherKey = function (Request $request, string $prefix) {
            $id = $request->user()?->currentAccessToken()?->id
                ?? $request->user()?->id
                ?? $request->ip();
            return $prefix . ':' . $id;
        };

        RateLimiter::for('launcher-lookup', function (Request $request) use ($launcherKey) {
            return Limit::perMinute(6000)->by($launcherKey($request, 'launcher-lookup'));
        });

        RateLimiter::for('launcher-browse', function (Request $request) use ($launcherKey) {
            return Limit::perMinute(600)->by($launcherKey($request, 'launcher-browse'));
        });

        RateLimiter::for('launcher-upload', function (Request $request) use ($launcherKey) {
            return Limit::perMinute(300)->by($launcherKey($request, 'launcher-upload'));
        });

        // Back-compat aliases for any leftover route still pointing at
        // the older single bucket. Same numeric limit as launcher-lookup
        // so legacy traffic isn't punished, but new routes should use
        // launcher-lookup / launcher-browse explicitly.
        RateLimiter::for('launcher-read', function (Request $request) use ($launcherKey) {
            return Limit::perMinute(6000)->by($launcherKey($request, 'launcher-read'));
        });
        RateLimiter::for('launcher', function (Request $request) use ($launcherKey) {
            return Limit::perMinute(6000)->by($launcherKey($request, 'launcher'));
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('general')
                ->group(base_path('routes/general.php'));

            Route::middleware(['web'])
                ->group(base_path('routes/tournaments.php'));


            Route::middleware('web')
                ->namespace('App\Http\Controllers\Clans')
                ->prefix('clans')
                ->group(base_path('routes/clans.php'));

            Route::middleware('web')
                ->group(base_path('routes/headhunter.php'));

            Route::middleware('web')
                ->group(base_path('routes/marketplace.php'));
        });
    }
}
