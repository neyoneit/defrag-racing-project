<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LauncherController extends Controller
{
    /**
     * Public download page for the desktop launcher.
     *
     * Fetches the latest release metadata from GitHub (cached 1 hour) and
     * passes a categorized assets map to the Inertia page so the frontend
     * can render per-platform download buttons without hardcoding URLs that
     * would rot on every new release.
     */
    public function page(): Response
    {
        $release = Cache::get('launcher:release');
        if ($release === null) {
            $release = $this->fetchLatestRelease();
            if ($release !== null) {
                Cache::put('launcher:release', $release, now()->addHour());
            }
        }

        $version = $release['tag_name'] ?? null;
        if ($version !== null) {
            $version = preg_replace('/^launcher-v/', '', $version);
        }

        $assets = collect($release['assets'] ?? []);
        $categorized = $this->categorizeAssets($assets);

        return Inertia::render('Launcher', [
            'version'    => $version,
            'published'  => $release['published_at'] ?? null,
            'assets'     => $categorized,
            'releaseUrl' => $release['html_url'] ?? 'https://github.com/Defrag-racing/defrag-racing-launcher/releases/latest',
            'changelogUrl' => 'https://github.com/Defrag-racing/defrag-racing-launcher/blob/main/CHANGELOG.md',
        ]);
    }

    /**
     * Fetch the latest published release from GitHub. Returns null on any
     * failure (network, 404 when no non-draft releases exist yet, rate
     * limit, malformed JSON). The page renders gracefully with no assets
     * in that case, and the caller decides whether to cache the result.
     */
    protected function fetchLatestRelease(): ?array
    {
        try {
            $response = Http::timeout(10)
                ->retry(2, 500, throw: false)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('https://api.github.com/repos/Defrag-racing/defrag-racing-launcher/releases/latest');

            if (! $response->successful()) {
                Log::warning('launcher release fetch failed', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('launcher release fetch exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Group GH release assets into windows/macos/linux buckets keyed by
     * recognised installer kinds. .sig signature files and source archives
     * are dropped - they exist for the updater + GitHub's source-code zips
     * but aren't useful as direct downloads from the web page.
     */
    protected function categorizeAssets(Collection $assets): array
    {
        $by = ['windows' => [], 'macos' => [], 'linux' => []];

        foreach ($assets as $asset) {
            $name = strtolower($asset['name'] ?? '');
            $size = $asset['size'] ?? 0;
            $url  = $asset['browser_download_url'] ?? null;
            if (! $url || ! $name) continue;
            if (str_ends_with($name, '.sig')) continue;
            if (str_ends_with($name, '.json')) continue;
            if (preg_match('/source.*\.(zip|tar\.gz)$/', $name)) continue;
            if (str_contains($name, 'updater') && str_ends_with($name, '.tar.gz')) continue;
            if (str_contains($name, 'updater') && str_ends_with($name, '.app.tar.gz')) continue;
            if (str_ends_with($name, '.app.tar.gz')) continue;

            $entry = [
                'name' => $asset['name'],
                'size' => $size,
                'url'  => $url,
            ];

            if (str_ends_with($name, '.msi')) {
                $by['windows'][] = $entry + ['kind' => 'msi', 'label' => 'Installer (.msi)', 'recommended' => true];
            } elseif (str_ends_with($name, '.exe')) {
                $by['windows'][] = $entry + ['kind' => 'exe', 'label' => 'Setup (.exe)', 'recommended' => false];
            } elseif (str_ends_with($name, '.dmg')) {
                $isArm = str_contains($name, 'aarch64') || str_contains($name, 'arm64');
                $by['macos'][] = $entry + [
                    'kind'  => $isArm ? 'dmg-arm' : 'dmg-intel',
                    'label' => $isArm ? 'Apple Silicon (.dmg)' : 'Intel (.dmg)',
                    'recommended' => $isArm,
                ];
            } elseif (str_ends_with($name, '.appimage')) {
                $by['linux'][] = $entry + ['kind' => 'appimage', 'label' => 'AppImage', 'recommended' => true];
            } elseif (str_ends_with($name, '.deb')) {
                $by['linux'][] = $entry + ['kind' => 'deb', 'label' => 'Debian / Ubuntu (.deb)', 'recommended' => false];
            } elseif (str_ends_with($name, '.rpm')) {
                $by['linux'][] = $entry + ['kind' => 'rpm', 'label' => 'Fedora / RHEL (.rpm)', 'recommended' => false];
            }
        }

        $sortOrder = ['msi', 'exe', 'dmg-arm', 'dmg-intel', 'appimage', 'deb', 'rpm'];
        foreach ($by as $os => $list) {
            usort($by[$os], fn($a, $b) => array_search($a['kind'], $sortOrder) <=> array_search($b['kind'], $sortOrder));
        }

        return $by;
    }


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
