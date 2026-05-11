<?php

namespace App\Http\Middleware;

use App\Models\ApiCallLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs every authenticated /api/* call into the `api_call_log` table
 * with user_id, token_id (if Bearer-token auth), route, status, and
 * response time. Used to spot heavy / abusive scrapers on a per-user
 * (or per-token) basis from Filament.
 *
 * Mounted on the `auth:sanctum,web`-protected route group in api.php,
 * so anonymous requests never reach this middleware. The actual write
 * happens AFTER the response is built, so it doesn't slow down the
 * user-facing latency.
 */
class LogApiCalls
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        // Best-effort: never throw out of here, never block the response.
        try {
            $user = $request->user();
            if ($user) {
                $token   = method_exists($user, 'currentAccessToken')
                    ? $user->currentAccessToken()
                    : null;
                // currentAccessToken returns TransientToken when the user
                // authenticated via session cookie — TransientToken has
                // no id. Only PersonalAccessToken is a real PAT row.
                $tokenId = $token instanceof \Laravel\Sanctum\PersonalAccessToken
                    ? $token->id
                    : null;

                $queryString = $request->getQueryString();

                ApiCallLog::create([
                    'user_id'         => $user->id,
                    'token_id'        => $tokenId,
                    'route'           => substr('/' . ltrim($request->path(), '/'), 0, 191),
                    'query_string'    => $queryString !== '' && $queryString !== null
                        ? substr($queryString, 0, 2000)
                        : null,
                    'method'          => $request->method(),
                    'ip'              => $request->ip(),
                    'response_status' => $response->getStatusCode(),
                    'response_ms'     => (int) ((microtime(true) - $start) * 1000),
                ]);
            }
        } catch (Throwable $e) {
            // Logging the logger going wrong — keep response flowing.
            Log::warning('LogApiCalls write failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }
}
