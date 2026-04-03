<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecalcMapRatingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public string $mapname,
        public string $physics,
        public string $mode,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (Cache::get('rating_recalc:status') === 'running') {
            $this->release(60);
            return;
        }

        Artisan::call('ratings:recalc-map', [
            'map' => $this->mapname,
            '--physics' => $this->physics,
            '--mode' => $this->mode,
        ]);
    }
}
