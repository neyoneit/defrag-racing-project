<?php

namespace App\Filament\Resources\DefragliveContestResource\Pages;

use App\Filament\Resources\DefragliveContestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDefragliveContest extends EditRecord
{
    protected static string $resource = DefragliveContestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
