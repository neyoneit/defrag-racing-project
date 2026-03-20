<?php

namespace App\Filament\Pages;

use App\Models\RenderedVideo;
use App\Models\SiteSetting;
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
            'isPaused' => SiteSetting::getBool('demome:paused', false),
            'isAutoQueuePaused' => SiteSetting::getBool('demome:auto_queue_paused', false),
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
            'unlisted_count' => RenderedVideo::where('status', 'completed')
                ->where('source', 'auto')
                ->where('publish_approved', false)
                ->whereNull('published_at')
                ->whereNotNull('youtube_video_id')
                ->count(),
            'unlisted_videos' => RenderedVideo::where('status', 'completed')
                ->where('source', 'auto')
                ->where('publish_approved', false)
                ->whereNull('published_at')
                ->whereNotNull('youtube_video_id')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get(),
            'publishing_count' => RenderedVideo::where('status', 'completed')
                ->where('publish_approved', true)
                ->whereNull('published_at')
                ->count(),
            'next_auto_publish' => Carbon::now()->next(Carbon::SUNDAY)->setTime(18, 0),
            'last_auto_publish' => SiteSetting::get('demome:last_auto_publish')
                ? Carbon::parse(SiteSetting::get('demome:last_auto_publish'))
                : null,
        ];
    }

    public function togglePause(): void
    {
        $currentState = SiteSetting::getBool('demome:paused', false);
        SiteSetting::set('demome:paused', !$currentState ? '1' : '0');

        Notification::make()
            ->title($currentState ? 'Rendering Unpaused' : 'Rendering Paused')
            ->body($currentState ? 'Demome will pick up renders on next poll.' : 'Demome will not pick up any videos until unpaused.')
            ->success()
            ->send();
    }

    public function toggleAutoQueue(): void
    {
        $currentState = SiteSetting::getBool('demome:auto_queue_paused', false);
        SiteSetting::set('demome:auto_queue_paused', !$currentState ? '1' : '0');

        Notification::make()
            ->title($currentState ? 'Auto-Queue Enabled' : 'Auto-Queue Disabled')
            ->body($currentState ? 'Verified records will be auto-added to render queue.' : 'Auto-queue stopped. User requests still work.')
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

    public function publishSingle(int $videoId): void
    {
        $video = RenderedVideo::find($videoId);
        if (!$video || $video->published_at || $video->publish_approved) {
            return;
        }

        $video->update(['publish_approved' => true]);

        Notification::make()
            ->title('Video Queued for Publishing')
            ->body("{$video->map_name} - {$video->player_name} will be published by demome on next cycle.")
            ->success()
            ->send();
    }

    public function publishUnlisted(): void
    {
        $count = RenderedVideo::where('status', 'completed')
            ->where('source', 'auto')
            ->where('publish_approved', false)
            ->whereNull('published_at')
            ->whereNotNull('youtube_video_id')
            ->count();

        if ($count === 0) {
            Notification::make()
                ->title('No Videos to Publish')
                ->body('There are no unlisted auto-rendered videos waiting to be published.')
                ->warning()
                ->send();
            return;
        }

        RenderedVideo::where('status', 'completed')
            ->where('source', 'auto')
            ->where('publish_approved', false)
            ->whereNull('published_at')
            ->whereNotNull('youtube_video_id')
            ->update(['publish_approved' => true]);

        Notification::make()
            ->title('Publish Triggered')
            ->body("{$count} unlisted videos will be changed to public by demome on next cycle.")
            ->success()
            ->send();
    }

    public function regenerateTokenAction(): Action
    {
        return Action::make('regenerateToken')
            ->label('Regenerate')
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Regenerate API Token')
            ->modalDescription('This will invalidate the current token. Demome will stop working until you update its settings.py with the new token. Type "yes" to confirm.')
            ->form([
                \Filament\Forms\Components\TextInput::make('confirmation')
                    ->label('Type "yes" to confirm')
                    ->required()
                    ->rule('in:yes'),
            ])
            ->action(fn () => $this->regenerateToken());
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
