<?php

namespace App\Filament\Pages;

use App\Services\RenderQueueService;
use App\Models\RenderedVideo;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class RenderQueuePreview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Render Queue';
    protected static ?string $navigationGroup = 'Demome';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.render-queue-preview';

    public array $poolCounts = [];
    public array $preview = [];
    public int $selectedTier = 0;
    public bool $loading = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->loadPoolCounts();
    }

    public function loadPoolCounts(): void
    {
        $this->poolCounts = RenderQueueService::getPoolCounts();
    }

    public function loadPreview(int $tier): void
    {
        $this->selectedTier = $tier;
        $candidates = RenderQueueService::getCandidatesForTier($tier, 50);
        $this->preview = $candidates->map(function ($demo) use ($tier) {
            $record = $demo->record;
            return [
                'id' => $demo->id,
                'map_name' => $demo->map_name,
                'player_name' => $demo->player_name,
                'physics' => $demo->physics,
                'time_ms' => $demo->time_ms,
                'time_fmt' => $demo->time_ms ? sprintf('%d:%02d.%03d', floor($demo->time_ms / 60000), floor(($demo->time_ms % 60000) / 1000), $demo->time_ms % 1000) : '-',
                'rank' => $record?->rank,
                'record_id' => $demo->record_id,
                'tier' => $tier,
            ];
        })->toArray();
    }

    public function queueDemo(int $demoId, int $tier): void
    {
        $demo = \App\Models\UploadedDemo::with('record')->find($demoId);
        if (!$demo) {
            Notification::make()->title('Demo not found')->danger()->send();
            return;
        }

        $existing = RenderedVideo::where('demo_id', $demoId)->first();
        if ($existing) {
            Notification::make()->title('Already in queue or rendered')->warning()->send();
            return;
        }

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
            'priority' => -1,
            'quality_tier' => $tier,
            'demo_url' => $demoUrl,
            'demo_filename' => $demo->original_filename,
        ]);

        Notification::make()->title("Queued: {$demo->map_name}")->success()->send();

        // Refresh preview
        if ($this->selectedTier) {
            $this->loadPreview($this->selectedTier);
        }
    }

    public function getRotationInfo(): array
    {
        $rotation = RenderQueueService::ROTATION;
        $counts = [];
        foreach ($rotation as $tier) {
            $label = RenderQueueService::TIER_LABELS[$tier];
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }
        return $counts;
    }

    public function getCurrentQueueStats(): array
    {
        return [
            'pending' => RenderedVideo::where('status', 'pending')->count(),
            'rendering' => RenderedVideo::where('status', 'rendering')->count(),
            'completed_today' => RenderedVideo::where('status', 'completed')
                ->where('updated_at', '>=', now()->startOfDay())->count(),
            'published_today' => RenderedVideo::where('status', 'completed')
                ->whereNotNull('published_at')
                ->where('published_at', '>=', now()->startOfDay())->count(),
        ];
    }
}
