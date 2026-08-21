<?php

namespace App\Filament\Resources\MarketplaceWorkTypeResource\Pages;

use App\Filament\Resources\MarketplaceWorkTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceWorkType extends EditRecord
{
    protected static string $resource = MarketplaceWorkTypeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A tab left blank writes ["label" => null]; drop those so the
        // fallback sees an empty language rather than an empty string.
        $data['translations'] = collect($data['translations'] ?? [])
            ->map(fn ($fields) => array_filter((array) $fields, fn ($value) => $value !== null && $value !== ''))
            ->filter()
            ->all() ?: null;

        if ($data['status'] === 'approved' && !$this->record->approved_at) {
            $data['approved_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
