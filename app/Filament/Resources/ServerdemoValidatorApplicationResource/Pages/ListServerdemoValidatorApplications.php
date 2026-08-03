<?php

namespace App\Filament\Resources\ServerdemoValidatorApplicationResource\Pages;

use App\Filament\Resources\ServerdemoValidatorApplicationResource;
use App\Models\ServerdemoValidatorVoteRound;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListServerdemoValidatorApplications extends ListRecords
{
    protected static string $resource = ServerdemoValidatorApplicationResource::class;

    /**
     * The vote is opened and closed by hand. No schedule: the round should
     * end when everyone has voted, which is not something a date knows.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('openVoting')
                ->label('Open voting round')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => ServerdemoValidatorVoteRound::current() === null)
                ->form([
                    \Filament\Forms\Components\TextInput::make('title')
                        ->label('Shown to voters (optional)')
                        ->placeholder('First round - pick who gets the position'),
                ])
                ->action(function (array $data) {
                    ServerdemoValidatorVoteRound::create([
                        'title' => $data['title'] ?? null,
                        'opened_at' => now(),
                        'opened_by' => auth()->id(),
                    ]);

                    Notification::make()->success()->title('Voting is open')->send();
                }),

            Actions\Action::make('closeVoting')
                ->label('Close voting round')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->visible(fn () => ServerdemoValidatorVoteRound::current() !== null)
                ->requiresConfirmation()
                ->modalDescription('Nobody can vote after this. The tally stays visible to you.')
                ->action(function () {
                    $round = ServerdemoValidatorVoteRound::current();
                    $round?->update(['closed_at' => now()]);

                    Notification::make()->success()->title('Voting closed')->send();
                }),
        ];
    }
}
