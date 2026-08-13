<?php

namespace App\Filament\Resources\CompMapReportResource\Pages;

use App\Filament\Resources\CompMapReportResource;
use Filament\Resources\Pages\ListRecords;

class ListCompMapReports extends ListRecords
{
    protected static string $resource = CompMapReportResource::class;

    /** Nothing here is created by hand - every row comes from a player. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
