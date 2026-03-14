<?php

namespace App\Filament\Resources\DemoAssignmentReportResource\Pages;

use App\Filament\Resources\DemoAssignmentReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDemoAssignmentReport extends EditRecord
{
    protected static string $resource = DemoAssignmentReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
