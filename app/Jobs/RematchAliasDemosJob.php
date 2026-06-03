<?php

namespace App\Jobs;

use App\Models\UploadedDemo;
use App\Services\DemoAutoAssigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Scan demos recorded under a just-added/removed alias nickname and refresh
 * their stored name-match attribution (suggested_user_id / matched_alias)
 * against the CURRENT alias set - immediately, instead of waiting for the
 * nightly demos:rematch-all.
 *
 * The map's live Demos Top re-resolves identity on every (re)compute, so the
 * leaderboard only needs the cache generation bumped to reflect a new alias.
 * This job is for the per-demo attribution other surfaces read (profile demo
 * lists, the demos browser) and for rebuilding the materialized queue ranks
 * on the maps that actually carry the nick.
 *
 * Matches on the normalised plain nickname, so demos whose player_name is
 * stored with Quake colour codes are left to the nightly rematch backstop.
 */
class RematchAliasDemosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $alias)
    {
    }

    public function handle(DemoAutoAssigner $assigner): void
    {
        $needle = mb_strtolower(trim($this->alias));
        if ($needle === '') {
            return;
        }

        $maps = [];
        UploadedDemo::whereRaw('LOWER(TRIM(player_name)) = ?', [$needle])
            ->whereNotNull('map_name')
            ->chunkById(500, function ($demos) use ($assigner, &$maps) {
                foreach ($demos as $demo) {
                    $assigner->updateNameMatchOnly($demo);
                    $maps[$demo->map_name] = true;
                }
            });

        // Re-group + rebuild only the maps that actually carry this nick.
        foreach (array_keys($maps) as $map) {
            Cache::increment('demostop_gen:' . $map);
            RebuildDemosTopRanksJob::dispatch($map);
        }
    }
}
