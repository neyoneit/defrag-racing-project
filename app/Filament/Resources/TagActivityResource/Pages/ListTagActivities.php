<?php

namespace App\Filament\Resources\TagActivityResource\Pages;

use App\Filament\Resources\TagActivityResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTagActivities extends ListRecords
{
    protected static string $resource = TagActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-queue-list'),
            'user_tags' => Tab::make('User Tagging')
                ->icon('heroicon-o-tag')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('action', ['added', 'removed'])),
        ];
    }
}
