<?php

namespace App\Filament\Resources\RenderedVideoResource\Pages;

use App\Filament\Resources\RenderedVideoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRenderedVideo extends EditRecord
{
    protected static string $resource = RenderedVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
