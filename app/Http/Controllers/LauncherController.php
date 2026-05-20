<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LauncherController extends Controller
{
    /**
     * Mirror of the launcher's signed update manifest. The launcher's
     * tauri-plugin-updater tries this endpoint first because GitHub is
     * intermittently blocked / slow for users in CN and parts of RU;
     * falling back to the GH Releases URL is the second endpoint in the
     * launcher's config.
     *
     * We cache the upstream response for 5 minutes — releases happen
     * rarely (manual tag push) and a freshly cut release stays fresh
     * within minutes either way. Caching also shields us from GH rate
     * limits if the launcher install base grows.
     *
     * Returns the upstream manifest verbatim. Do NOT rewrite signatures,
     * platforms map, etc. — the launcher verifies the signature with the
     * embedded pubkey, so any rewrite would invalidate it.
     */
    public function latestManifest(): JsonResponse
    {
        $manifest = Cache::remember('launcher:latest-manifest', now()->addMinutes(5), function () {
            $response = Http::timeout(10)
                ->retry(2, 500)
                ->get('https://github.com/Defrag-racing/defrag-racing-launcher/releases/latest/download/latest.json');

            if (! $response->successful()) {
                Log::warning('launcher manifest fetch failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        });

        // Upstream unreachable. Returning 503 (rather than caching the
        // failure) lets the launcher fall through to its GH endpoint
        // immediately on the next check.
        if ($manifest === null) {
            return response()->json(
                ['error' => 'upstream manifest unavailable'],
                503
            );
        }

        return response()->json($manifest);
    }
}
