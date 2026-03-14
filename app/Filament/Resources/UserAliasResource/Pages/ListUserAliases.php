<?php

namespace App\Filament\Resources\UserAliasResource\Pages;

use App\Filament\Resources\UserAliasResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserAliases extends ListRecords
{
    protected static string $resource = UserAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
