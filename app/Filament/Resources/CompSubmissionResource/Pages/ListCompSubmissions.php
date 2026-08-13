<?php

namespace App\Filament\Resources\CompSubmissionResource\Pages;

use App\Filament\Resources\CompSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListCompSubmissions extends ListRecords
{
    protected static string $resource = CompSubmissionResource::class;

    /** Entries come from players uploading. None is created here. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
