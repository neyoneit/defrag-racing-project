<?php

namespace App\Filament\Resources\BotVisitResource\Widgets;

use App\Models\BotVisit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BotStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $week = now()->subDays(7)->toDateString();

        $todayHits = BotVisit::where('date', $today)->sum('hits');
        $yesterdayHits = BotVisit::where('date', $yesterday)->sum('hits');
        $weekHits = BotVisit::where('date', '>=', $week)->sum('hits');

        $todayUniqueIps = BotVisit::where('date', $today)->distinct('ip')->count('ip');
        $weekUniqueIps = BotVisit::where('date', '>=', $week)->distinct('ip')->count('ip');

        $todayUniquePaths = BotVisit::where('date', $today)->distinct('path')->count('path');

        $topBot = BotVisit::where('date', $today)
            ->selectRaw('ip, SUM(hits) as total_hits')
            ->groupBy('ip')
            ->orderByDesc('total_hits')
            ->first();

        return [
            Stat::make('Today Hits', number_format($todayHits))
                ->description($yesterdayHits > 0 ? 'Yesterday: ' . number_format($yesterdayHits) : 'No data yesterday')
                ->color($todayHits >= 100 ? 'danger' : ($todayHits >= 20 ? 'warning' : 'success'))
                ->icon('heroicon-o-cursor-arrow-rays'),
            Stat::make('Week Hits', number_format($weekHits))
                ->description('Last 7 days')
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Unique Bot IPs Today', $todayUniqueIps)
                ->description($weekUniqueIps . ' this week')
                ->icon('heroicon-o-globe-alt'),
            Stat::make('Unique Paths Today', $todayUniquePaths)
                ->description('Pages targeted')
                ->icon('heroicon-o-map'),
            Stat::make('Most Active Bot', $topBot ? $topBot->ip : '-')
                ->description($topBot ? number_format($topBot->total_hits) . ' hits today' : 'No bots today')
                ->color($topBot && $topBot->total_hits >= 50 ? 'danger' : 'gray')
                ->icon('heroicon-o-bug-ant'),
        ];
    }
}
