<?php

namespace App\Filament\Resources\AliasReportResource\Pages;

use App\Filament\Resources\AliasReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAliasReport extends EditRecord
{
    protected static string $resource = AliasReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
