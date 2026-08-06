<?php

namespace App\Filament\Resources\PlayerSelfReportResource\Pages;

use App\Filament\Resources\PlayerSelfReportResource;
use Filament\Resources\Pages\ListRecords;

class ListPlayerSelfReports extends ListRecords
{
    protected static string $resource = PlayerSelfReportResource::class;

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
