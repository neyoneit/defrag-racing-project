<?php

namespace App\Console\Commands;

use App\Services\Comps\CompScheduler;
use Illuminate\Console\Command;

/**
 * Moves comps along. Run every minute; on almost every one of them there is
 * nothing due and it returns immediately.
 *
 * Everything it does is driven off timestamps rather than off "has it been a
 * week", so a missed run - a deploy, an outage - catches up on the next tick
 * instead of leaving a round stuck open.
 */
class CompsTick extends Command
{
    protected $signature = 'comps:tick';

    protected $description = 'Open, close, start and finish comps rounds as their times come due';

    public function handle(CompScheduler $scheduler): int
    {
        $done = $scheduler->tick();

        if (empty($done)) {
            $this->line('Nothing due.');

            return self::SUCCESS;
        }

        foreach ($done as $line) {
            $this->info($line);
        }

        return self::SUCCESS;
    }
}
