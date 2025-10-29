<?php

namespace App\Filament\Resources\HeadhunterChallengeResource\Pages;

use App\Filament\Resources\HeadhunterChallengeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHeadhunterChallenge extends EditRecord
{
    protected static string $resource = HeadhunterChallengeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
