<?php

namespace App\Filament\Resources\ApiCallLogResource\Pages;

use App\Filament\Resources\ApiCallLogResource;
use Filament\Resources\Pages\ListRecords;

class ListApiCallLogs extends ListRecords
{
    protected static string $resource = ApiCallLogResource::class;

    public function getTableRecordKey($record): string
    {
        // The main list aggregates GROUP BY user_id, so rows have no
        // primary id. user_id is unique per row and works as the key.
        return (string) ($record->user_id ?? '0');
    }

    protected function getHeaderWidgets(): array
    {
        return ApiCallLogResource::getWidgets();
    }
}
