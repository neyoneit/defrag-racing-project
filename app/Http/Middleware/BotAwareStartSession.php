<?php

namespace App\Http\Middleware;

use App\Services\BotDetector;
use Closure;
use Illuminate\Session\Middleware\StartSession;

/**
 * Skip session start for identified bots so their requests don't
 * create sessions table rows. Bot crawlers rarely persist cookies,
 * so every request was creating a new row — the sessions table had
 * grown to ~1.8M rows in 30 days from bot traffic alone.
 */
class BotAwareStartSession extends StartSession
{
    public function handle($request, Closure $next)
    {
        if (BotDetector::isBot($request->userAgent() ?? '')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
