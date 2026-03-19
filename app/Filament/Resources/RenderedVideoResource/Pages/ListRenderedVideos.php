<?php

namespace App\Filament\Resources\RenderedVideoResource\Pages;

use App\Filament\Resources\RenderedVideoResource;
use Filament\Resources\Pages\ListRecords;

class ListRenderedVideos extends ListRecords
{
    protected static string $resource = RenderedVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
