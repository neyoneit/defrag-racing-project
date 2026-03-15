<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Jobs\ProcessDemoJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UnlockStuckDemos extends Command
{
    protected $signature = 'demos:unlock-stuck {--minutes=15 : Minutes after which a stuck demo is re-queued}';
    protected $description = 'Re-queue demos stuck in processing or uploaded status';

    public function handle()
    {
        $minutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($minutes);
        $count = 0;

        // Demos stuck in 'processing' - worker crashed mid-job
        $stuckProcessing = UploadedDemo::where('status', 'processing')
            ->where('updated_at', '<', $threshold)
            ->get();

        foreach ($stuckProcessing as $demo) {
            $demo->update(['status' => 'uploaded', 'processing_output' => null]);
            ProcessDemoJob::dispatch($demo)->onQueue('demos');
            Log::info("Unlocked stuck demo #{$demo->id} (was processing since {$demo->updated_at})");
            $count++;
        }

        // Demos stuck in 'uploaded'/'pending' - job lost from Redis queue (e.g. restart/deploy)
        $stuckUploaded = UploadedDemo::whereIn('status', ['uploaded', 'pending'])
            ->where('updated_at', '<', $threshold)
            ->get();

        foreach ($stuckUploaded as $demo) {
            ProcessDemoJob::dispatch($demo)->onQueue('demos');
            Log::info("Re-queued orphaned demo #{$demo->id} (uploaded since {$demo->updated_at})");
            $count++;
        }

        $this->info($count > 0
            ? "Re-queued {$count} stuck demo(s) ({$stuckProcessing->count()} processing, {$stuckUploaded->count()} uploaded/pending)."
            : 'No stuck demos found.'
        );

        return 0;
    }
}
