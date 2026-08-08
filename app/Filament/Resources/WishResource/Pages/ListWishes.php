<?php

namespace App\Filament\Resources\WishResource\Pages;

use App\Filament\Resources\WishResource;
use App\Models\Wish;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWishes extends ListRecords
{
    protected static string $resource = WishResource::class;

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }

    /**
     * Status tabs across the top, with the queue first: a wish waiting for
     * approval is invisible to everyone but its author, and one whose author
     * asked for it to go is waiting on an answer. Those are the two that cost
     * something to miss, so they lead and carry a count.
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All')
                ->badge(Wish::count()),

            'waiting' => Tab::make('Waiting for approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('approved_at'))
                ->badge(Wish::whereNull('approved_at')->count())
                ->badgeColor('warning'),

            'removal' => Tab::make('Removal requested')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('removal_requested_at'))
                ->badge(Wish::whereNotNull('removal_requested_at')->count())
                ->badgeColor('danger'),
        ];

        $counts = Wish::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach (Wish::STATUSES as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status))
                ->badge($counts[$status] ?? 0)
                ->badgeColor(match ($status) {
                    'planned' => 'info',
                    'done' => 'success',
                    'rejected' => 'danger',
                    default => 'gray',
                });
        }

        return $tabs;
    }
}
