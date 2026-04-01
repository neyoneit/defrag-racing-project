<?php

namespace App\Console\Commands;

use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildRenderQueue extends Command
{
    protected $signature = 'demome:build-queue {--dry-run : Show what would be added without adding}';

    protected $description = 'Build the full render queue: WR demos first, then all demos ordered by rank/speed';

    public function handle(): int
    {
        $this->info('Building render queue...');

        // Get demo IDs that already have a rendered video (any status)
        $existingDemoIds = RenderedVideo::whereNotNull('demo_id')
            ->pluck('demo_id')
            ->toArray();
        $existingSet = array_flip($existingDemoIds);

        $added = 0;
        $skipped = 0;

        // Phase 1: WR demos (rank=1, has record_id) - ordered by shortest time first
        $wrDemos = UploadedDemo::query()
            ->whereNotNull('record_id')
            ->whereHas('record', fn ($q) => $q->where('rank', 1)->whereNull('deleted_at'))
            ->with('record')
            ->orderBy('time_ms', 'asc')
            ->get();

        $this->info("Phase 1: {$wrDemos->count()} WR demos found");

        foreach ($wrDemos as $demo) {
            if (isset($existingSet[$demo->id])) {
                $skipped++;
                continue;
            }

            if (!$this->option('dry-run')) {
                $this->createQueueItem($demo, 1);
            }
            $existingSet[$demo->id] = true;
            $added++;
        }

        $this->info("Phase 1 done: {$added} WR demos added, {$skipped} skipped (already rendered)");

        // Phase 2: All remaining demos ordered by rank (best first), then by time
        // Includes both online (with record_id) and offline demos
        $phase2Added = 0;
        $phase2Skipped = 0;

        // Online demos with rank - ordered by rank ASC, time ASC
        $onlineDemos = DB::select("
            SELECT d.id
            FROM uploaded_demos d
            JOIN records r ON r.id = d.record_id
            WHERE r.rank > 1
            AND r.deleted_at IS NULL
            AND d.time_ms > 0
            AND d.status IN ('assigned', 'fallback-assigned', 'processed')
            ORDER BY r.rank ASC, d.time_ms ASC
        ");

        $onlineIds = collect($onlineDemos)->pluck('id')->toArray();
        $this->info("Phase 2a: " . count($onlineIds) . " non-WR online demos found");

        $chunks = array_chunk($onlineIds, 500);
        foreach ($chunks as $chunk) {
            $demos = UploadedDemo::whereIn('id', $chunk)
                ->with('record')
                ->get()
                ->keyBy('id');

            foreach ($chunk as $id) {
                if (isset($existingSet[$id])) {
                    $phase2Skipped++;
                    continue;
                }

                $demo = $demos->get($id);
                if (!$demo) continue;

                if (!$this->option('dry-run')) {
                    $this->createQueueItem($demo, 2);
                }
                $existingSet[$id] = true;
                $phase2Added++;
            }
        }

        // Offline demos (no record_id) - ordered by time ASC (fastest first)
        $offlineDemos = DB::select("
            SELECT d.id
            FROM uploaded_demos d
            WHERE d.record_id IS NULL
            AND d.time_ms > 0
            AND d.status IN ('assigned', 'fallback-assigned', 'processed')
            ORDER BY d.time_ms ASC
        ");

        $offlineIds = collect($offlineDemos)->pluck('id')->toArray();
        $this->info("Phase 2b: " . count($offlineIds) . " offline demos found");

        $chunks = array_chunk($offlineIds, 500);
        foreach ($chunks as $chunk) {
            $demos = UploadedDemo::whereIn('id', $chunk)->get()->keyBy('id');

            foreach ($chunk as $id) {
                if (isset($existingSet[$id])) {
                    $phase2Skipped++;
                    continue;
                }

                $demo = $demos->get($id);
                if (!$demo) continue;

                if (!$this->option('dry-run')) {
                    $this->createQueueItem($demo, 3);
                }
                $existingSet[$id] = true;
                $phase2Added++;
            }
        }

        $this->info("Phase 2 done: {$phase2Added} demos added, {$phase2Skipped} skipped");
        $this->info("Total added: " . ($added + $phase2Added));

        return self::SUCCESS;
    }

    private function createQueueItem(UploadedDemo $demo, int $priority): void
    {
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
            'priority' => $priority,
            'demo_url' => $demoUrl,
            'demo_filename' => $demo->original_filename,
        ]);
    }
}
