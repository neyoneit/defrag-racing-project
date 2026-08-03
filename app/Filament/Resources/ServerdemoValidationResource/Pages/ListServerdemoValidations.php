<?php

namespace App\Filament\Resources\ServerdemoValidationResource\Pages;

use App\Filament\Resources\ServerdemoValidationResource;
use App\Models\ServerdemoValidationCase;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListServerdemoValidations extends ListRecords
{
    protected static string $resource = ServerdemoValidationResource::class;

    /**
     * Serverdemos and uploaded demos stay apart.
     *
     * A report on a RECORD is checked against the serverdemo the game server
     * wrote - private, never browsable, handed out one file at a time. A
     * report on an uploaded demo is about a file the player published here,
     * which anyone can already download. Same reviewers, different evidence
     * and different rules, so they do not belong in one list.
     */
    public function getTabs(): array
    {
        $countOpen = fn (string $kind) => ServerdemoValidationResource::getEloquentQuery()
            ->where('kind', $kind)
            ->whereNull('validation_closed_at')
            ->count() ?: null;

        return [
            'serverdemos' => Tab::make('Serverdemos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kind', ServerdemoValidationCase::KIND_SERVERDEMO))
                ->badge(fn () => $countOpen(ServerdemoValidationCase::KIND_SERVERDEMO)),

            'public_demos' => Tab::make('Public demos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kind', ServerdemoValidationCase::KIND_PUBLIC_DEMO))
                ->badge(fn () => $countOpen(ServerdemoValidationCase::KIND_PUBLIC_DEMO)),

            'all' => Tab::make('All'),
        ];
    }
}
