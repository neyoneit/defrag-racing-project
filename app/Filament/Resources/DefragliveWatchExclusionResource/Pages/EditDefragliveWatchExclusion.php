<?php

namespace App\Filament\Resources\DefragliveWatchExclusionResource\Pages;

use App\Filament\Resources\DefragliveWatchExclusionResource;
use App\Services\DefragliveWatchService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDefragliveWatchExclusion extends EditRecord
{
    protected static string $resource = DefragliveWatchExclusionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['name_clean'] ?? null)) {
            $data['name_clean'] = app(DefragliveWatchService::class)->cleanName($data['player_name']);
        }

        return $data;
    }
}
