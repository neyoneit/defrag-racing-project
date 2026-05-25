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

        // Launcher API buckets, keyed per token so two devices on the same
        // account don't share quota. We split by work intensity:
        //
        //  - launcher-read: lookup-by-hash, servers, notifications.
        //    These are single indexed selects + serializer; the cost is
        //    HTTP overhead, not DB load. The launcher fires one
        //    lookup-by-hash per cache-miss demo during a rescan, so a
        //    10k-demo backlog from a long-time player on first run can
        //    blow past anything tight. 6000/min = 100/sec covers even
        //    a "no CPU limit" disk-bound rescan without hitting 429,
        //    and well under any abuse threshold (it's one indexed read).
        //
        //  - launcher-upload: upload-demo. This actually does work
        //    (multipart receive, file move, ProcessDemoJob dispatch),
        //    plus the file payload is order(s) of magnitude larger
        //    than a lookup. 60/min = 1/sec is enough for live recording
        //    and twice that for a backlog drain; anything faster would
        //    saturate ProcessDemoJob workers anyway.
        //
        // Falls back to user id or IP when somehow no token is present
        // (shouldn't happen on the launcher routes, belt & braces).
        $launcherKey = function (Request $request, string $prefix) {
            $id = $request->user()?->currentAccessToken()?->id
                ?? $request->user()?->id
                ?? $request->ip();
            return $prefix . ':' . $id;
        };

        RateLimiter::for('launcher-read', function (Request $request) use ($launcherKey) {
            return Limit::perMinute(6000)->by($launcherKey($request, 'launcher-read'));
        });

        RateLimiter::for('launcher-upload', function (Request $request) use ($launcherKey) {
            return Limit::perMinute(60)->by($launcherKey($request, 'launcher-upload'));
        });

        // Back-compat alias for any leftover route still pointing at the
        // old single bucket. Same limit as launcher-read so nothing
        // legacy gets stuck on the old 120/min.
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
