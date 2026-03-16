<?php

namespace App\Filament\Resources\MaplistResource\Pages;

use App\Filament\Resources\MaplistResource;
use Filament\Resources\Pages\ListRecords;

class ListMaplists extends ListRecords
{
    protected static string $resource = MaplistResource::class;

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
