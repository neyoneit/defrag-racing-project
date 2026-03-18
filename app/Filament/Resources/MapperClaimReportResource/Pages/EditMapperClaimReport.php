<?php

namespace App\Filament\Resources\MapperClaimReportResource\Pages;

use App\Filament\Resources\MapperClaimReportResource;
use Filament\Resources\Pages\EditRecord;

class EditMapperClaimReport extends EditRecord
{
    protected static string $resource = MapperClaimReportResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? 'pending') !== 'pending') {
            $data['resolved_by'] = auth()->id();
            $data['resolved_at'] = now();
        }
        return $data;
    }
}
