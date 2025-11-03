<?php

namespace App\Filament\Resources\AliasReportResource\Pages;

use App\Filament\Resources\AliasReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAliasReports extends ListRecords
{
    protected static string $resource = AliasReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Alias reports are created by users, not admins
        ];
    }

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
