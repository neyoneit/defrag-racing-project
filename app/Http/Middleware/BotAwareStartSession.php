<?php

namespace App\Http\Middleware;

use App\Services\BotDetector;
use Illuminate\Session\Middleware\StartSession;

/**
 * Run StartSession normally so downstream middleware (ShareErrorsFromSession,
 * VerifyCsrfToken, HandleInertiaRequests) still has a session to read — but
 * skip the terminal save() for bot traffic so their session never lands in
 * the sessions table. Prevents bot bloat (~1.8M rows / 30 days) without
 * breaking endpoints hit by headless clients like the DefragLive bot,
 * which send a `python-requests/*` UA and expect JSON responses.
 */
class BotAwareStartSession extends StartSession
{
    /**
     * Laravel 10's StartSession does NOT define a `terminate()` method —
     * session persistence happens inside handle() right before the
     * response is returned. The original implementation called
     * `parent::terminate()` which threw "undefined method" on every
     * request that exercised terminable middleware (logged endlessly to
     * laravel.log without breaking the response, but noisy).
     *
     * Keep the override as a no-op so that:
     *   a) future Laravel upgrades that *do* add terminate() don't
     *      retroactively start saving bot sessions,
     *   b) middleware introspection still recognizes this class as
     *      terminable (preserves original intent).
     */
    public function terminate($request, $response)
    {
        if (BotDetector::isBot($request->userAgent() ?? '')) {
            return;
        }
        // Parent has no terminate() in Laravel 10.x — saveSession was
        // already called from handle() before the response went out, so
        // there's nothing left to do here.
    }
}
