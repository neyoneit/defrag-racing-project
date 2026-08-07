<?php

namespace App\Filament\Resources\PlayerSelfReportResource\Widgets;

use App\Models\PlayerSelfReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four numbers worth knowing before opening a single request: how much is
 * waiting, how many people it is spread over, and how much of it is queued
 * behind the MDD merge rather than behind an admin.
 */
class AmnestyOverview extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $waiting = PlayerSelfReport::pending()->count();
        $players = PlayerSelfReport::distinct()->count('mdd_id');
        $onMerge = PlayerSelfReport::pending()->where('handling', 'on_merge')->count();
        $done = PlayerSelfReport::whereNotNull('processed_at')->count();

        return [
            Stat::make('Waiting on you', $waiting - $onMerge)
                ->description($waiting ? 'Asked to be hidden now' : 'Nothing to do')
                ->color($waiting - $onMerge > 0 ? 'warning' : 'success'),

            Stat::make('Queued for the merge', $onMerge)
                ->description('No action needed until the databases merge')
                ->color('gray'),

            Stat::make('Players', $players)
                ->description('People who have used the amnesty'),

            Stat::make('Runs taken down', $done)
                ->description('Hidden or resolved by a better run')
                ->color('success'),
        ];
    }
}
