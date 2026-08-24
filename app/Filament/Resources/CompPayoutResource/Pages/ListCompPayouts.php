<?php

namespace App\Filament\Resources\CompPayoutResource\Pages;

use App\Filament\Resources\CompPayoutResource;
use App\Services\Comps\PrizePayouts;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCompPayouts extends ListRecords
{
    protected static string $resource = CompPayoutResource::class;

    /**
     * Rows are written when a round finishes. The button is for the rounds
     * that finished before any of this existed, and it is safe to press at any
     * time - it only ever adds what is missing.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backfill')
                ->label('Find missing prizes')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Walks every finished round and adds an owed row for any winner that has none. Nothing already here is changed.')
                ->action(function () {
                    $created = app(PrizePayouts::class)->backfill();

                    Notification::make()
                        ->success()
                        ->title($created > 0 ? "{$created} prize(s) added" : 'Nothing missing')
                        ->send();
                }),
        ];
    }
}
