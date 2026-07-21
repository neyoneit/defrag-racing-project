<?php

namespace App\Filament\Resources\DownloadResource\Pages;

use App\Filament\Resources\DownloadResource;
use Filament\Resources\Pages\ListRecords;

class ListDownloads extends ListRecords
{
    protected static string $resource = DownloadResource::class;

    // No create action: entries come from user uploads and sync commands, not
    // from the admin panel.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
