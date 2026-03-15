<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Jobs\ProcessDemoJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UnlockStuckDemos extends Command
{
    protected $signature = 'demos:unlock-stuck {--minutes=15 : Minutes after which a processing demo is considered stuck}';
    protected $description = 'Reset demos stuck in processing status and re-queue them';

    public function handle()
    {
        $minutes = (int) $this->option('minutes');

        $stuckDemos = UploadedDemo::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->get();

        if ($stuckDemos->isEmpty()) {
            $this->info('No stuck demos found.');
            return 0;
        }

        $count = $stuckDemos->count();
        $this->info("Found {$count} stuck demo(s). Resetting and re-queuing...");

        foreach ($stuckDemos as $demo) {
            $demo->update([
                'status' => 'uploaded',
                'processing_output' => null,
            ]);

            ProcessDemoJob::dispatch($demo)->onQueue('demos');

            Log::info("Unlocked stuck demo #{$demo->id} (was processing since {$demo->updated_at})");
        }

        $this->info("Reset {$count} demo(s) back to queue.");

        return 0;
    }
}
