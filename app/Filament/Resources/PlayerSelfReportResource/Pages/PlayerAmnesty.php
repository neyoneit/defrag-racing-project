<?php

namespace App\Filament\Resources\PlayerSelfReportResource\Pages;

use App\Filament\Resources\PlayerSelfReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

/**
 * One player's withdrawals.
 *
 * The mdd id is held in a public property rather than read from the route on
 * every call: Livewire's follow-up requests go to its own endpoint, where the
 * route parameters of this page do not exist.
 */
class PlayerAmnesty extends ListRecords
{
    protected static string $resource = PlayerSelfReportResource::class;

    public ?string $mdd = null;

    public function mount(): void
    {
        $this->mdd = request()->route('mdd');

        parent::mount();
    }

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }

    public function getTitle(): string
    {
        return PlayerSelfReportResource::playerName($this->mdd);
    }

    public function getSubheading(): ?string
    {
        return 'MDD #' . $this->mdd . ' - everything this player took down';
    }

    public function table(Table $table): Table
    {
        return PlayerSelfReportResource::detailTable($table, $this->mdd);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to the list of players')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PlayerSelfReportResource::getUrl('index')),
        ];
    }
}
