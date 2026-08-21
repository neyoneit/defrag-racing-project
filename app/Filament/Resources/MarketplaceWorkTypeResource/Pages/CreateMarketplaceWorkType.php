<?php

namespace App\Filament\Resources\MarketplaceWorkTypeResource\Pages;

use App\Filament\Resources\MarketplaceWorkTypeResource;
use App\Models\MarketplaceWorkType;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplaceWorkType extends CreateRecord
{
    protected static string $resource = MarketplaceWorkTypeResource::class;

    /**
     * The slug is derived, never typed: it is what every listing stores, so a
     * typo here would be a typo on every listing of that type.
     * A type an admin adds needs no approving.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = MarketplaceWorkType::slugFor($data['label']);
        $data['status'] = $data['status'] ?? 'approved';
        $data['approved_at'] = $data['status'] === 'approved' ? now() : null;
        $data['is_core'] = false;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
