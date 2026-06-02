<?php

namespace App\Jobs;

use App\Services\DemosTopRankService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Recompute the materialized Demos Top ranking for a single map after its
 * field changed (new MDD record, new offline record, demo (re)assignment).
 *
 * ShouldBeUnique coalesces a burst of changes on the same map (e.g. a scrape
 * inserting several records) into one rebuild while one is already queued.
 */
class RebuildDemosTopRanksJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(public string $mapName)
    {
    }

    public function uniqueId(): string
    {
        return $this->mapName;
    }

    public function handle(DemosTopRankService $service): void
    {
        if ($this->mapName === '') {
            return;
        }

        $service->rebuildMap($this->mapName);

        // Invalidate the web map detail's cached Demos Top (MapsController::
        // buildDemosTopReps) by bumping this map's cache generation. Redis INCR
        // creates the key at 1 when missing.
        Cache::increment('demostop_gen:' . $this->mapName);
    }
}
