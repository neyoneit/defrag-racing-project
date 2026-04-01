<?php

namespace App\Console\Commands;

use App\Models\Record;
use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use Illuminate\Console\Command;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class PopulateRenderQueue extends Command
{
    protected $signature = 'demome:populate-queue {--force : Force populate even if demome is not idle}';

    protected $description = 'Populate the render queue with auto-render items when demome is idle';

    public function handle()
    {
        if (SiteSetting::getBool('demome:auto_queue_paused', false) && !$this->option('force')) {
            $this->info('Auto-queue is disabled. Skipping. (Use --force to override)');
            return;
        }

        // Check if demome is idle for 10+ minutes (unless forced)
        if (!$this->option('force')) {
            $lastHeartbeat = Cache::get('demome:last_heartbeat');
            $currentStatus = Cache::get('demome:current_status', 'unknown');

            if ($currentStatus === 'rendering' || $currentStatus === 'uploading') {
                $this->info('Demome is busy. Skipping.');
                return;
            }

            // If no heartbeat at all, demome might be offline - still populate so it has work when it comes back
        }

        // Check if there are already pending items
        $pendingCount = RenderedVideo::where('status', 'pending')->count();
        if ($pendingCount >= 5) {
            $this->info("Already {$pendingCount} pending items. Skipping.");
            return;
        }

        $added = 0;
        $limit = 5 - $pendingCount;

        // Priority 1: Verified World Records (rank=1, has demo, has online record link)
        if ($limit > 0) {
            $added += $this->populatePriority(1, $limit);
            $limit = 10 - $pendingCount - $added;
        }

        // Priority 2: Other verified records (rank > 1, has demo + online record)
        if ($limit > 0) {
            $added += $this->populatePriority(2, $limit);
            $limit = 10 - $pendingCount - $added;
        }

        // Priority 3: Any demos with online records
        if ($limit > 0) {
            $added += $this->populatePriority(3, $limit);
        }

        $this->info("Added {$added} items to render queue.");
    }

    private function populatePriority(int $priority, int $limit): int
    {
        $query = UploadedDemo::query()
            ->whereNotNull('record_id')
            ->whereHas('record', function ($q) use ($priority) {
                $q->whereNull('deleted_at');
                if ($priority === 1) {
                    $q->where('rank', 1);
                } elseif ($priority === 2) {
                    $q->where('rank', '>', 1);
                }
            })
            ->whereDoesntHave('renderedVideo')
            ->with('record')
            ->orderByDesc('id')
            ->limit($limit);

        $demos = $query->get();
        $added = 0;

        foreach ($demos as $demo) {
            $demoUrl = config('app.url') . "/api/demome/download-demo/{$demo->id}";

            RenderedVideo::create([
                'map_name' => $demo->map_name ?? $demo->record->mapname,
                'player_name' => $demo->player_name ?? $demo->record->name,
                'physics' => $demo->physics ?? $demo->record->physics,
                'time_ms' => $demo->time_ms ?? $demo->record->time,
                'gametype' => $demo->gametype ?? $demo->record->gametype,
                'record_id' => $demo->record_id,
                'demo_id' => $demo->id,
                'source' => 'auto',
                'status' => 'pending',
                'priority' => $priority,
                'demo_url' => $demoUrl,
                'demo_filename' => $demo->original_filename,
            ]);

            $added++;
        }

        return $added;
    }
}
