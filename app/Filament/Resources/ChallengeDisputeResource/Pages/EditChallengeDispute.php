<?php

namespace App\Filament\Resources\ChallengeDisputeResource\Pages;

use App\Filament\Resources\ChallengeDisputeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChallengeDispute extends EditRecord
{
    protected static string $resource = ChallengeDisputeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
