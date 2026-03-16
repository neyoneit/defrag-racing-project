<?php

namespace App\Filament\Resources\RecordFlagResource\Pages;

use App\Filament\Resources\RecordFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListRecordFlags extends ListRecords
{
    protected static string $resource = RecordFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
