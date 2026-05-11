<?php

namespace App\Filament\Resources\ApiCallLogResource\Widgets;

use App\Models\ApiCallLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApiCallStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $last24h = ApiCallLog::where('created_at', '>=', now()->subDay())->count();
        $last7d  = ApiCallLog::where('created_at', '>=', now()->subDays(7))->count();
        $errors  = ApiCallLog::where('created_at', '>=', now()->subDay())
            ->where('response_status', '>=', 400)
            ->count();
        $byToken = ApiCallLog::where('created_at', '>=', now()->subDay())
            ->whereNotNull('token_id')
            ->count();

        return [
            Stat::make('Last 24h', number_format($last24h))
                ->description('all API hits')
                ->color('primary'),
            Stat::make('Last 7d', number_format($last7d))
                ->description('all API hits')
                ->color('gray'),
            Stat::make('Errors 24h', number_format($errors))
                ->description('4xx + 5xx responses')
                ->color($errors > 0 ? 'warning' : 'success'),
            Stat::make('Token-auth 24h', number_format($byToken))
                ->description('Bearer-token calls (vs browser session)')
                ->color('info'),
        ];
    }
}
