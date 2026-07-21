<?php

namespace App\Filament\Pages;

use App\Jobs\BuildServerBundleJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Admin view for the auto-generated server bundle (dfsv-core.tar). Shows when
 * it was last rebuilt (from the marker the build command writes) and offers a
 * manual "Rebuild now" that dispatches the same job the daily scheduler runs.
 */
class ServerBundle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Server Bundle';
    protected static ?string $navigationGroup = 'Storage';
    protected static ?int $navigationSort = 42;
    protected static string $view = 'filament.pages.server-bundle';

    private const DISK = 'dl_storage';
    private const MARKER = 'dfsv-core.version.json';
    private const PUBLIC_BASE = 'https://dl.defrag.racing/downloads/';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->isAdmin() || $user?->hasModeratorPermission('storage_browser');
    }

    /** Build marker written by bundle:build-server (engine build + mod + built_at). */
    public function getMarkerProperty(): ?array
    {
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists(self::MARKER)) {
            return null;
        }
        return json_decode((string) $disk->get(self::MARKER), true) ?: null;
    }

    /** The three archives on the downloads disk with size + public URL. */
    public function getFilesProperty(): array
    {
        $disk = Storage::disk(self::DISK);
        $out = [];
        foreach ([
            'dfsv-core.tar'        => 'Core bundle (engine + mod + modules) - rebuilt automatically',
            'dfsv-baseq3.tar'      => 'baseq3 paks - static, one-time',
            'dfsv-core-static.tar' => 'Static base input (modules + qagame + ip4db + uglifix)',
        ] as $name => $desc) {
            $exists = $disk->exists($name);
            $out[] = [
                'name'   => $name,
                'desc'   => $desc,
                'exists' => $exists,
                'size'   => $exists ? $disk->size($name) : null,
                'url'    => self::PUBLIC_BASE . $name,
            ];
        }
        return $out;
    }

    public function getStatusProperty(): ?string
    {
        return Cache::get('bundle_build:status');
    }

    /** @return string[] */
    public function getLogProperty(): array
    {
        return Cache::get('bundle_build:log', []);
    }

    public function rebuild(): void
    {
        if (Cache::get('bundle_build:status') === 'running') {
            Notification::make()->title('A rebuild is already running')->warning()->send();
            return;
        }

        Cache::put('bundle_build:status', 'running', 3600);
        Cache::put('bundle_build:log', ['[' . now()->format('H:i:s') . '] Dispatched, waiting for worker...'], 3600);

        BuildServerBundleJob::dispatch();

        Notification::make()
            ->title('Rebuild dispatched')
            ->body('Running in the background - the log below updates as it goes.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rebuild')
                ->label('Rebuild now')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Fetch the latest oDFe engine and DeFRaG mod and repackage dfsv-core.tar.')
                ->action('rebuild'),
        ];
    }

    public function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '-';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return round($value, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
