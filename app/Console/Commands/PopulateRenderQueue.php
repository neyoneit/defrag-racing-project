<?php

namespace App\Console\Commands;

use App\Models\RenderedVideo;
use App\Services\RenderQueueService;
use Illuminate\Console\Command;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class PopulateRenderQueue extends Command
{
    protected $signature = 'demome:populate-queue {--force : Force populate even if demome is not idle}';

    protected $description = 'Populate the render queue with auto-render items using tiered rotation';

    public function handle()
    {
        if (SiteSetting::getBool('demome:auto_queue_paused', false) && !$this->option('force')) {
            $this->info('Auto-queue is disabled. Skipping. (Use --force to override)');
            return;
        }

        // Check if demome is busy (unless forced)
        if (!$this->option('force')) {
            $currentStatus = Cache::get('demome:current_status', 'unknown');
            if ($currentStatus === 'rendering' || $currentStatus === 'uploading') {
                $this->info('Demome is busy. Skipping.');
                return;
            }
        }

        // Check pending count
        $pendingCount = RenderedVideo::where('status', 'pending')->count();
        if ($pendingCount >= 5) {
            $this->info("Already {$pendingCount} pending items. Skipping.");
            return;
        }

        $limit = 5 - $pendingCount;
        $batch = RenderQueueService::getNextBatch($limit);
        $added = 0;

        foreach ($batch as $item) {
            $demo = $item['demo'];
            $tier = $item['tier'];

            $demoUrl = config('app.url') . "/api/demome/download-demo/{$demo->id}";

            RenderedVideo::create([
                'map_name' => $demo->map_name ?? $demo->record?->mapname,
                'player_name' => $demo->player_name ?? $demo->record?->name ?? 'Unknown',
                'physics' => $demo->physics ?? $demo->record?->physics,
                'time_ms' => $demo->time_ms ?? $demo->record?->time,
                'gametype' => $demo->gametype ?? $demo->record?->gametype ?? 'df',
                'record_id' => $demo->record_id,
                'demo_id' => $demo->id,
                'source' => 'auto',
                'status' => 'pending',
                'priority' => 1,
                'quality_tier' => $tier,
                'demo_url' => $demoUrl,
                'demo_filename' => $demo->original_filename,
            ]);

            $this->line("  Added: {$demo->map_name} ({$demo->player_name}) - " . RenderQueueService::TIER_LABELS[$tier]);
            $added++;
        }

        $this->info("Added {$added} items to render queue.");
    }
}
