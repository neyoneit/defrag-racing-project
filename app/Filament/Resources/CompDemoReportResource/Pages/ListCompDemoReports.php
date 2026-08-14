<?php

namespace App\Filament\Resources\CompDemoReportResource\Pages;

use App\Filament\Resources\CompDemoReportResource;
use Filament\Resources\Pages\ListRecords;

class ListCompDemoReports extends ListRecords
{
    protected static string $resource = CompDemoReportResource::class;

    /** Nothing here is created by hand - every row comes from a player. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
