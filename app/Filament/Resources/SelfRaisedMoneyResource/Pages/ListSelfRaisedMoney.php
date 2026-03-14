<?php

namespace App\Filament\Resources\SelfRaisedMoneyResource\Pages;

use App\Filament\Resources\SelfRaisedMoneyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSelfRaisedMoney extends ListRecords
{
    protected static string $resource = SelfRaisedMoneyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
