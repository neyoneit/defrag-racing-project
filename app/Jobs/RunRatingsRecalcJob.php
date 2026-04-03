<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunRatingsRecalcJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;
    public int $uniqueFor = 3600;

    private const LOG_KEY = 'rating_recalc:log';
    private const STATUS_KEY = 'rating_recalc:status';

    public function __construct(
        public array $physicsList,
        public array $categories,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $total = count($this->physicsList) * count($this->categories);
        Cache::put(self::STATUS_KEY, 'running', 86400);
        $this->log("Recalculation started - {$total} tasks queued");

        $done = 0;

        foreach ($this->physicsList as $physics) {
            foreach ($this->categories as $category) {
                $done++;
                $this->log("[{$done}/{$total}] Starting {$physics} / {$category}...");

                try {
                    $start = microtime(true);

                    Artisan::call('ratings:calculate', [
                        '--physics' => $physics,
                        '--category' => $category,
                    ]);

                    $duration = round(microtime(true) - $start, 1);
                    $this->log("[{$done}/{$total}] Completed {$physics} / {$category} in {$duration}s");
                } catch (\Throwable $e) {
                    $this->log("[{$done}/{$total}] FAILED {$physics} / {$category}: {$e->getMessage()}");
                }
            }
        }

        $this->log("Recalculation finished - {$done}/{$total} completed");
        Cache::put(self::STATUS_KEY, 'done', 86400);
    }

    private function log(string $message): void
    {
        $lines = Cache::get(self::LOG_KEY, []);
        $lines[] = '[' . now()->format('H:i:s') . '] ' . $message;
        Cache::put(self::LOG_KEY, $lines, 86400);
    }

    public function failed(\Throwable $e): void
    {
        $this->log("JOB FAILED: {$e->getMessage()}");
        Cache::put(self::STATUS_KEY, 'failed', 86400);
    }
}
