<?php

namespace App\Filament\Resources\DonationGoalResource\Pages;

use App\Filament\Resources\DonationGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDonationGoal extends EditRecord
{
    protected static string $resource = DonationGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
