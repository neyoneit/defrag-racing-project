<?php

namespace App\Console\Commands;

use App\Services\MapStatsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RebuildMapStats extends Command
{
    protected $signature = 'mapstats:rebuild {--export : also write a gzipped JSON snapshot to storage/app/public/exports/maps-stats.json[.gz]}';
    protected $description = 'Refresh the cached MapStats payload (and optionally export it as a gzipped JSON snapshot).';

    public function handle(MapStatsService $stats): int
    {
        $this->info('Clearing MapStats cache...');
        $stats->clearCache();

        $this->info('Rebuilding aggregates (cold)...');
        $start = microtime(true);
        $payload = $stats->all();
        $ms = (int) round((microtime(true) - $start) * 1000);
        $this->info("Rebuilt in {$ms} ms — " . count($payload['cpm']) . ' CPM + ' . count($payload['vq3']) . ' VQ3 maps.');

        if (!$this->option('export')) {
            return self::SUCCESS;
        }

        // Wired but not scheduled. Snapshot lives under storage/app/public/exports/
        // so `php artisan storage:link` exposes it under /storage/exports/...
        // — turn the cron back on once a public consumer needs it.
        $disk = Storage::disk('public');
        $disk->makeDirectory('exports');

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $disk->put('exports/maps-stats.json', $json);
        $disk->put('exports/maps-stats.json.gz', gzencode($json, 6));

        $this->info('Wrote storage/app/public/exports/maps-stats.json (' . round(strlen($json) / 1024) . ' kB) and .gz (' . round(strlen(gzencode($json, 6)) / 1024) . ' kB)');

        return self::SUCCESS;
    }
}
