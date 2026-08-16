<?php

namespace App\Console\Commands;

use App\Models\Comp;
use App\Models\CompRound;
use App\Models\UploadedDemo;
use App\Services\Comps\UploadGuard;
use Illuminate\Console\Command;

/**
 * Re-run the comps upload guard over demos of the maps a round has decided.
 *
 * Written for the day the guard only looked at rounds already being played:
 * the ballot closes a day before play starts, and every run uploaded in that
 * day went public - listed on the map, in the records, and queued for a
 * YouTube render. The guard covers that window now, but the demos that came
 * through it while it did not are already sitting in the open, and nothing
 * re-reads a demo once the parser is done with it.
 *
 * Safe to run at any time: it only ever looks at demos of a map a `locked` or
 * `active` round has decided, and hands each one to the same guard the parser
 * calls, which decides on the demo's own contents.
 */
class CompsReguard extends Command
{
    protected $signature = 'comps:reguard {--dry-run : List what would be held, change nothing}';

    protected $description = 'Re-apply the comps hold to demos of maps a decided or running round is using';

    public function handle(UploadGuard $guard): int
    {
        $dryRun = $this->option('dry-run');

        $rounds = CompRound::whereIn('status', ['locked', 'active'])
            ->where('ends_at', '>', now())
            ->whereHas('comp', fn ($q) => $q->where('type', Comp::WEEKLY))
            ->with('maps.map')
            ->get();

        if ($rounds->isEmpty()) {
            $this->info('No decided or running round right now, nothing to guard.');

            return self::SUCCESS;
        }

        $held = 0;

        foreach ($rounds as $round) {
            foreach ($round->maps as $roundMap) {
                if (! $roundMap->map) {
                    continue;
                }

                $name = trim($roundMap->map->name);

                // Only the ones that are visible: a demo already held needs
                // nothing, and the guard would leave it alone anyway.
                $demos = UploadedDemo::withUnreleasedComps()
                    ->where(fn ($q) => $q->whereNull('comps_hidden_until')
                        ->orWhere('comps_hidden_until', '<=', now()))
                    ->whereRaw('LOWER(map_name) = ?', [mb_strtolower($name)])
                    ->where('created_at', '>=', $round->voting_opens_at ?? $round->created_at)
                    ->get();

                foreach ($demos as $demo) {
                    $this->line(sprintf(
                        '  demo %d  %s  (%s, round %d %s)',
                        $demo->id,
                        $demo->original_filename ?: $name,
                        $roundMap->physics,
                        $round->id,
                        $round->status,
                    ));

                    if ($dryRun) {
                        continue;
                    }

                    $guard->apply($demo->fresh());

                    if ($demo->fresh()->comps_hidden_until?->isFuture()) {
                        $held++;
                    }
                }
            }
        }

        $this->info($dryRun
            ? 'Dry run - nothing changed.'
            : "Held {$held} demo(s).");

        return self::SUCCESS;
    }
}
