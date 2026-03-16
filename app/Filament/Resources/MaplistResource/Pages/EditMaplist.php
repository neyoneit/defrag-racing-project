<?php

namespace App\Filament\Resources\MaplistResource\Pages;

use App\Filament\Resources\MaplistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaplist extends EditRecord
{
    protected static string $resource = MaplistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    $record->favorites()->detach();
                    $record->likes()->detach();
                }),
        ];
    }
}
