<?php

namespace App\Filament\Resources\UserAliasResource\Pages;

use App\Filament\Resources\UserAliasResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserAlias extends EditRecord
{
    protected static string $resource = UserAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
