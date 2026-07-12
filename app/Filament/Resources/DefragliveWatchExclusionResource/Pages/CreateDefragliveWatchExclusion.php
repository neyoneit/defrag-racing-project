<?php

namespace App\Filament\Resources\DefragliveWatchExclusionResource\Pages;

use App\Filament\Resources\DefragliveWatchExclusionResource;
use App\Services\DefragliveWatchService;
use Filament\Resources\Pages\CreateRecord;

class CreateDefragliveWatchExclusion extends CreateRecord
{
    protected static string $resource = DefragliveWatchExclusionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Never persist an empty matching key: derive it from the display name
        // when the admin left the auto-filled field blank.
        if (blank($data['name_clean'] ?? null)) {
            $data['name_clean'] = app(DefragliveWatchService::class)->cleanName($data['player_name']);
        }

        return $data;
    }
}
