<?php

namespace App\Filament\Resources\BotVisitResource\Widgets;

use App\Models\BotVisit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BotStatsOverview extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $week = now()->subDays(7)->toDateString();
        $lastHour = now()->subHour();

        $lastHourHits = BotVisit::where('updated_at', '>=', $lastHour)->sum('hits');
        $todayHits = BotVisit::where('date', $today)->sum('hits');
        $yesterdayHits = BotVisit::where('date', $yesterday)->sum('hits');
        $weekHits = BotVisit::where('date', '>=', $week)->sum('hits');

        $todayUniqueIps = BotVisit::where('date', $today)->distinct('ip')->count('ip');
        $weekUniqueIps = BotVisit::where('date', '>=', $week)->distinct('ip')->count('ip');

        $todayUniquePaths = BotVisit::where('date', $today)->distinct('path')->count('path');

        $topBot = BotVisit::where('date', $today)
            ->selectRaw('ip, user_agent, SUM(hits) as total_hits')
            ->groupBy('ip', 'user_agent')
            ->orderByDesc('total_hits')
            ->first();

        $lastSeen = BotVisit::orderByDesc('updated_at')->first();

        return [
            Stat::make('Last Hour', number_format($lastHourHits))
                ->description('Bot hits in last 60 min')
                ->color($lastHourHits >= 50 ? 'danger' : ($lastHourHits >= 10 ? 'warning' : 'success'))
                ->icon('heroicon-o-clock'),
            Stat::make('Today', number_format($todayHits))
                ->description($yesterdayHits > 0 ? 'Yesterday: ' . number_format($yesterdayHits) : 'No data yesterday')
                ->color($todayHits >= 100 ? 'danger' : ($todayHits >= 20 ? 'warning' : 'success'))
                ->icon('heroicon-o-cursor-arrow-rays'),
            Stat::make('This Week', number_format($weekHits))
                ->description('Last 7 days')
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Unique IPs', $todayUniqueIps)
                ->description($weekUniqueIps . ' this week / ' . $todayUniquePaths . ' paths today')
                ->icon('heroicon-o-globe-alt'),
            Stat::make('Top Bot', $topBot ? $topBot->ip : '-')
                ->description($topBot ? number_format($topBot->total_hits) . ' hits - ' . \Illuminate\Support\Str::limit($topBot->user_agent, 30) : 'No bots today')
                ->color($topBot && $topBot->total_hits >= 50 ? 'danger' : 'gray')
                ->icon('heroicon-o-bug-ant'),
            Stat::make('Last Seen', $lastSeen ? $lastSeen->updated_at->diffForHumans() : '-')
                ->description($lastSeen ? $lastSeen->path : '')
                ->icon('heroicon-o-eye'),
        ];
    }
}
