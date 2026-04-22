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
    public function terminate($request, $response)
    {
        if (BotDetector::isBot($request->userAgent() ?? '')) {
            return;
        }

        parent::terminate($request, $response);
    }
}
