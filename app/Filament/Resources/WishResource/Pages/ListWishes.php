<?php

namespace App\Filament\Resources\WishResource\Pages;

use App\Filament\Resources\WishResource;
use Filament\Resources\Pages\ListRecords;

class ListWishes extends ListRecords
{
    protected static string $resource = WishResource::class;

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
