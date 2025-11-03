<?php

namespace App\Filament\Resources\DemoAssignmentReportResource\Pages;

use App\Filament\Resources\DemoAssignmentReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDemoAssignmentReports extends ListRecords
{
    protected static string $resource = DemoAssignmentReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Demo reports are created by users, not admins
        ];
    }

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
