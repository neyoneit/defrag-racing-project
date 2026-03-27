<?php

namespace App\Filament\Resources\TagActivityResource\Pages;

use App\Filament\Resources\TagActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListTagActivities extends ListRecords
{
    protected static string $resource = TagActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
