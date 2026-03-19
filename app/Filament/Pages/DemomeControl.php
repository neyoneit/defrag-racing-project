<?php

namespace App\Filament\Pages;

use App\Models\RenderedVideo;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DemomeControl extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Demome Control';

    protected static ?string $navigationGroup = 'Demome';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.demome-control';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        $lastHeartbeat = Cache::get('demome:last_heartbeat');
        $isOnline = $lastHeartbeat && Carbon::parse($lastHeartbeat)->diffInMinutes(now()) < 5;
        $currentVideoId = Cache::get('demome:current_video_id');
        $currentVideo = $currentVideoId ? RenderedVideo::find($currentVideoId) : null;

        return [
            'apiToken' => config('services.demome.api_token') ?: 'Not set',
            'isPaused' => Cache::get('demome:paused', false),
            'isOnline' => $isOnline,
            'currentStatus' => Cache::get('demome:current_status', 'unknown'),
            'lastHeartbeat' => $lastHeartbeat ? Carbon::parse($lastHeartbeat)->diffForHumans() : 'Never',
            'currentVideo' => $currentVideo,
            'stats' => [
                'pending' => RenderedVideo::where('status', 'pending')->count(),
                'rendering' => RenderedVideo::where('status', 'rendering')->count(),
                'completed_total' => RenderedVideo::where('status', 'completed')->count(),
                'completed_today' => RenderedVideo::where('status', 'completed')
                    ->whereDate('updated_at', today())->count(),
                'failed' => RenderedVideo::where('status', 'failed')->count(),
                'total_render_hours' => round(
                    RenderedVideo::where('status', 'completed')->sum('render_duration_seconds') / 3600, 1
                ),
            ],
            'queue_by_priority' => [
                'wr' => RenderedVideo::where('status', 'pending')->where('priority', 1)->count(),
                'verified' => RenderedVideo::where('status', 'pending')->where('priority', 2)->count(),
                'normal' => RenderedVideo::where('status', 'pending')->where('priority', 3)->count(),
            ],
        ];
    }

    public function togglePause(): void
    {
        $currentState = Cache::get('demome:paused', false);
        Cache::forever('demome:paused', !$currentState);

        Notification::make()
            ->title($currentState ? 'Demome Unpaused' : 'Demome Paused')
            ->body($currentState ? 'Demome will pick up new renders on next poll.' : 'Demome will not receive new items until unpaused.')
            ->success()
            ->send();
    }

    public function populateQueue(): void
    {
        \Artisan::call('demome:populate-queue', ['--force' => true]);

        Notification::make()
            ->title('Queue Populated')
            ->body(\Artisan::output())
            ->success()
            ->send();
    }

    public function regenerateToken(): void
    {
        $newToken = Str::random(64);

        // Update .env file
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (str_contains($envContent, 'DEMOME_API_TOKEN=')) {
            $envContent = preg_replace('/DEMOME_API_TOKEN=.*/', "DEMOME_API_TOKEN={$newToken}", $envContent);
        } else {
            $envContent .= "\nDEMOME_API_TOKEN={$newToken}\n";
        }

        file_put_contents($envPath, $envContent);

        // Update runtime config (Octane keeps config in memory)
        config()->set('services.demome.api_token', $newToken);

        // Clear config cache for next worker restart
        \Artisan::call('config:clear');

        Notification::make()
            ->title('API Token Regenerated')
            ->body("New token: {$newToken} - Update this in demome's settings.py and restart demome.")
            ->warning()
            ->persistent()
            ->send();
    }
}
