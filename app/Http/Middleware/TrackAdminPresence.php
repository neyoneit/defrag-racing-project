<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackAdminPresence
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->is_moderator)) {
            $presence = Cache::get('admin:presence', []);

            // Parse current activity from URL
            $path = $request->path();
            $activity = $this->parseActivity($path);

            $presence[$user->id] = [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->profile_photo_path,
                'is_admin' => $user->isAdmin(),
                'activity' => $activity,
                'path' => $path,
                'last_seen' => now()->timestamp,
            ];

            // Remove stale entries (older than 5 minutes)
            $cutoff = now()->timestamp - 300;
            $presence = array_filter($presence, fn ($p) => $p['last_seen'] > $cutoff);

            Cache::put('admin:presence', $presence, 600);
        }

        return $next($request);
    }

    private function parseActivity(string $path): string
    {
        // Remove panel prefix
        $path = preg_replace('#^defraghq/?#', '', $path);

        if ($path === '' || $path === '/') return 'Dashboard';

        // Match resource patterns: resource/create, resource/123/edit
        if (preg_match('#^([^/]+)/create$#', $path, $m)) {
            return 'Creating ' . $this->humanize($m[1]);
        }
        if (preg_match('#^([^/]+)/\d+/edit$#', $path, $m)) {
            return 'Editing ' . $this->humanize($m[1]);
        }
        if (preg_match('#^([^/]+)$#', $path, $m)) {
            return 'Browsing ' . $this->humanize($m[1]);
        }

        return 'Active';
    }

    private function humanize(string $slug): string
    {
        return ucfirst(str_replace('-', ' ', $slug));
    }
}
