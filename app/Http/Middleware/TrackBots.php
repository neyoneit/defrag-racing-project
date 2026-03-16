<?php

namespace App\Http\Middleware;

use App\Models\BotVisit;
use App\Services\BotDetector;
use Closure;
use Illuminate\Http\Request;

class TrackBots
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $ua = $request->userAgent() ?? '';

        if (BotDetector::isBot($ua)) {
            $date = now()->toDateString();
            $ip = $request->header('CF-Connecting-IP', $request->ip());
            $path = substr($request->path(), 0, 500);
            $method = $request->method();
            $status = $response->getStatusCode();

            // Aggregate: increment hits if same date+ip+ua+path exists
            BotVisit::where('date', $date)
                ->where('ip', $ip)
                ->where('user_agent', $ua)
                ->where('path', $path)
                ->where('method', $method)
                ->first()
                ?->increment('hits')
                ?? BotVisit::create([
                    'date' => $date,
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'path' => $path,
                    'method' => $method,
                    'hits' => 1,
                    'status_code' => $status,
                ]);
        }

        return $response;
    }
}
