<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Services\RenderQueueService;
use Illuminate\Console\Command;

class DemomeAutoPublish extends Command
{
    protected $signature = 'demome:auto-publish';

    protected $description = 'Auto-publish: mark a tiered mix of unlisted auto-rendered videos for publishing';

    public function handle()
    {
        // Use tiered rotation - respects mix, no map duplicates
        $batch = RenderQueueService::getNextPublishBatch(12);

        if ($batch->isEmpty()) {
            $this->info('No unlisted videos matching the tier rotation are available.');
            return;
        }

        $tierCounts = [];
        foreach ($batch as $video) {
            $video->update(['publish_approved' => true]);
            $tierLabel = RenderQueueService::TIER_LABELS[$video->quality_tier] ?? 'Unknown';
            $tierCounts[$tierLabel] = ($tierCounts[$tierLabel] ?? 0) + 1;
        }

        SiteSetting::set('demome:last_auto_publish', now()->toIso8601String());

        $breakdown = collect($tierCounts)->map(fn($c, $l) => "{$c}x {$l}")->join(', ');
        $this->info("Marked {$batch->count()} videos for publishing. Mix: {$breakdown}");
    }
}
