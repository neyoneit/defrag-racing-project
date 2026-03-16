<?php

namespace App\Filament\Resources\BotVisitResource\Pages;

use App\Filament\Resources\BotVisitResource;
use App\Models\BotVisit;
use App\Services\BotDetector;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBotVisits extends ListRecords
{
    protected static string $resource = BotVisitResource::class;

    public function getMaxContentWidth(): string|null
    {
        return 'full';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BotVisitResource\Widgets\BotStatsOverview::class,
            BotVisitResource\Widgets\BotHitsChart::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Bots'),
            'fake' => Tab::make('Fake Bots')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw("user_agent NOT REGEXP 'Googlebot|Bingbot|Slurp|DuckDuckBot|Baiduspider|YandexBot|facebookexternalhit|Twitterbot|LinkedInBot|Discordbot'")),
            'verified' => Tab::make('Verified Bots')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw("user_agent REGEXP 'Googlebot|Bingbot|Slurp|DuckDuckBot|Baiduspider|YandexBot|facebookexternalhit|Twitterbot|LinkedInBot|Discordbot'")),
        ];
    }
}
