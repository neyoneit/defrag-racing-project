<?php

namespace App\Filament\Resources\MarketplaceReviewResource\Pages;

use App\Filament\Resources\MarketplaceReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceReview extends EditRecord
{
    protected static string $resource = MarketplaceReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
