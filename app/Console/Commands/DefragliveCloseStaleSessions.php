<?php

namespace App\Console\Commands;

use App\Services\DefragliveWatchService;
use Illuminate\Console\Command;

/**
 * Close any watch session left open after the bot stopped sending serverstate
 * (crash / offline / paused). Scheduled every minute so dangling sessions don't
 * mis-credit dead time and the public "currently watching" stays honest.
 */
class DefragliveCloseStaleSessions extends Command
{
    protected $signature = 'defraglive:close-stale-sessions';

    protected $description = 'Close DefragLive watch sessions whose last tick is older than the gap cap';

    public function handle(DefragliveWatchService $service): int
    {
        $closed = $service->closeStaleSessions();
        $this->info("Closed {$closed} stale watch session(s).");

        return self::SUCCESS;
    }
}
