<?php

namespace App\Filament\Resources\MarketplaceWorkTypeResource\Pages;

use App\Filament\Resources\MarketplaceWorkTypeResource;
use App\Models\MarketplaceWorkType;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMarketplaceWorkTypes extends ListRecords
{
    protected static string $resource = MarketplaceWorkTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New work type'),
        ];
    }

    /** Waiting first: those are the ones somebody is waiting on. */
    public function getTabs(): array
    {
        $counts = MarketplaceWorkType::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => Tab::make('Waiting for approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge($counts['pending'] ?? 0)
                ->badgeColor('warning'),

            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge($counts['approved'] ?? 0)
                ->badgeColor('success'),

            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge($counts['rejected'] ?? 0)
                ->badgeColor('danger'),

            'all' => Tab::make('All')->badge($counts->sum()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return ($this->getModel()::where('status', 'pending')->exists()) ? 'pending' : 'approved';
    }
}
