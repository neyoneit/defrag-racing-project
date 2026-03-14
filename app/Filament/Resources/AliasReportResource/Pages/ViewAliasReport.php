<?php

namespace App\Filament\Resources\AliasReportResource\Pages;

use App\Filament\Resources\AliasReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAliasReport extends ViewRecord
{
    protected static string $resource = AliasReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
