<?php

namespace App\Filament\Pages;

use App\Models\DefragliveWatchSession;
use App\Models\Map;
use App\Support\Q3Color;
use Filament\Pages\Page;

/**
 * Read-only scrollable log of what the bot has spectated: who, on which map,
 * from when to when, and for how long. Backed entirely by the watch sessions
 * we already record for the contest (no data of its own). A feed, not a table,
 * to match the Live Chat panel.
 */
class DefragliveSpectateLog extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Spectate Log';

    protected static ?string $navigationGroup = 'DefragLive';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.defraglive-spectate-log';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    private static function duration(int $s): string
    {
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;

        return $h ? "{$h}h {$m}m" : ($m ? "{$m}m {$sec}s" : "{$sec}s");
    }

    protected function getViewData(): array
    {
        $sessions = DefragliveWatchSession::with('user:id,name,plain_name')
            ->orderByDesc('started_at')
            ->limit(300)
            ->get();

        // Batch-resolve map thumbnails for the visible maps (no N+1).
        $thumbs = Map::whereIn('name', $sessions->pluck('mapname')->filter()->unique()->values())
            ->pluck('thumbnail', 'name');

        $rows = $sessions->map(function (DefragliveWatchSession $s) use ($thumbs) {
            return [
                'id' => $s->id,
                'date' => optional($s->started_at)->format('M j'),
                'from' => optional($s->started_at)->format('H:i:s'),
                'to' => $s->ended_at ? $s->ended_at->format('H:i:s') : null,
                'live' => $s->ended_at === null,
                // Open session = still watching; show the live span to now.
                'duration' => self::duration(
                    $s->ended_at ? (int) $s->seconds : max(0, (int) $s->started_at->diffInSeconds(now()))
                ),
                'player_html' => Q3Color::toHtml($s->player_name),
                'mapname' => $s->mapname,
                'map_thumb' => $s->mapname ? ($thumbs[$s->mapname] ?? null) : null,
                'user' => $s->user ? [
                    'id' => $s->user->id,
                    'name' => $s->user->plain_name ?: $s->user->name,
                ] : null,
            ];
        });

        return [
            'rows' => $rows,
            'total' => DefragliveWatchSession::count(),
        ];
    }
}
