<?php

namespace App\Filament\Resources\DefragliveWatchExclusionResource\Pages;

use App\Filament\Resources\DefragliveWatchExclusionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDefragliveWatchExclusions extends ListRecords
{
    protected static string $resource = DefragliveWatchExclusionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
