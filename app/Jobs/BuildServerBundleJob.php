<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Runs `bundle:build-server --force` in the background so the admin "Rebuild
 * now" button returns immediately. Status/log land in the cache for the
 * Server Bundle page to poll.
 */
class BuildServerBundleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        Cache::put('bundle_build:status', 'running', 3600);
        Cache::put('bundle_build:log', ['[' . now()->format('H:i:s') . '] Building...'], 3600);

        try {
            $code = Artisan::call('bundle:build-server', ['--force' => true]);
            $out = array_values(array_filter(array_map('trim', explode("\n", Artisan::output()))));
            $out[] = '[' . now()->format('H:i:s') . '] ' . ($code === 0 ? 'Done.' : 'Failed.');

            Cache::put('bundle_build:log', $out, 3600);
            Cache::put('bundle_build:status', $code === 0 ? 'done' : 'error', 3600);
        } catch (\Throwable $e) {
            Cache::put('bundle_build:log', ['Error: ' . $e->getMessage()], 3600);
            Cache::put('bundle_build:status', 'error', 3600);
        }
    }
}
