<?php

namespace App\Console\Commands;

use App\Services\DefragliveJsonWriter;
use Illuminate\Console\Command;

/**
 * Rewrites the public console.json/serverstate.json from the DB. Scheduled
 * every minute (Kernel) to match the cadence of the old cron `docker cp` it
 * replaces; the live extension still gets realtime updates over the WS, this
 * file is only its initial-load / poll snapshot.
 */
class DefragliveWriteJson extends Command
{
    protected $signature = 'defraglive:write-json';

    protected $description = 'Rebuild the public DefragLive console.json/serverstate.json from the DB (replaces the legacy bridge cron docker cp)';

    public function handle(DefragliveJsonWriter $writer): int
    {
        if (!$writer->write()) {
            $this->warn('services.defraglive.public_path not set - writer disabled, no files written.');

            return self::SUCCESS;
        }

        $this->info('Wrote console.json + serverstate.json to ' . config('services.defraglive.public_path'));

        return self::SUCCESS;
    }
}
