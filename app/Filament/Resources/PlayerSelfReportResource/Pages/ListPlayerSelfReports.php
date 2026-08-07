<?php

namespace App\Filament\Resources\PlayerSelfReportResource\Pages;

use App\Filament\Resources\PlayerSelfReportResource;
use App\Filament\Resources\PlayerSelfReportResource\Widgets\AmnestyOverview;
use Filament\Resources\Pages\ListRecords;

class ListPlayerSelfReports extends ListRecords
{
    protected static string $resource = PlayerSelfReportResource::class;

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AmnestyOverview::class,
        ];
    }
}
