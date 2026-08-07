<?php

namespace App\Console\Commands;

use App\Models\PlayerSelfReport;
use App\Models\Record;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Close the amnesty requests their own author settled by running again.
 *
 * MDD reports personal bests only, so a faster run replaces the old record in
 * our table by itself: the disowned time is already off the board and its row
 * has moved to the history. An admin pressing "hide" on that would be hiding
 * something that is not there, so the request resolves itself as beaten.
 *
 * A slower run is invisible to us and changes nothing here - that is why this
 * only ever fires on an improvement, and why beating the time is the only
 * version of "I fixed it myself" we can actually see.
 *
 * The row is kept. After the MDD merge we get the full time history, and the
 * fact that a player disowned one of those old times is what makes the history
 * cleanable rather than just long.
 */
class ResolveBeatenAmnestyRequests extends Command
{
    protected $signature = 'amnesty:resolve-beaten';

    protected $description = 'Mark amnesty requests as resolved when the player has beaten the time themselves';

    public function handle(): int
    {
        $resolved = 0;

        PlayerSelfReport::pending()
            ->whereNotNull('record_id')
            ->whereNotNull('mdd_id')
            ->cursor()
            ->each(function (PlayerSelfReport $report) use (&$resolved) {
                $live = Record::where('mdd_id', $report->mdd_id)
                    ->where('mapname', $report->mapname)
                    ->where('physics', $report->physics)
                    ->where('mode', $report->mode)
                    ->first();

                // Still the same row: nothing has happened on that map.
                if (! $live || (int) $live->id === (int) $report->record_id) {
                    return;
                }

                // A different row that is not an improvement is not the player
                // fixing anything - it is the record having been replaced for
                // some other reason, and that still needs an admin.
                if ($report->time !== null && (int) $live->time >= (int) $report->time) {
                    return;
                }

                $report->update([
                    'processed_at' => now(),
                    'resolution' => 'beaten',
                ]);

                $resolved++;

                Log::info('Amnesty request resolved by a new run', [
                    'self_report_id' => $report->id,
                    'mdd_id' => $report->mdd_id,
                    'mapname' => $report->mapname,
                    'old_time' => $report->time,
                    'new_time' => $live->time,
                ]);
            });

        $this->info("Resolved {$resolved} request(s) beaten by a new run.");

        return self::SUCCESS;
    }
}
