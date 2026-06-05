<?php

namespace App\Console\Commands;

use App\Models\DefragliveContest;
use App\Services\DefragliveWatchService;
use Illuminate\Console\Command;

/**
 * Draw the watch-time-weighted raffle winner for a contest. Normally triggered
 * from the Filament admin, but exposed as a command for scripting / re-runs.
 */
class DefragliveDrawContest extends Command
{
    protected $signature = 'defraglive:draw-contest {contest : Contest id}';

    protected $description = 'Draw the DefragLive watch-time raffle winner for a contest';

    public function handle(DefragliveWatchService $service): int
    {
        $contest = DefragliveContest::find($this->argument('contest'));
        if (!$contest) {
            $this->error('Contest not found.');

            return self::FAILURE;
        }

        $winner = $service->draw($contest);
        if (!$winner) {
            $this->warn('No eligible entrants (nobody accrued at least one ticket). Contest left undrawn.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Winner: %s (%d s watched, %d/%d tickets, winning ticket %d).',
            $contest->winner_name,
            $contest->winner_seconds,
            $contest->winner_tickets,
            $contest->total_tickets,
            $contest->winning_ticket
        ));

        return self::SUCCESS;
    }
}
