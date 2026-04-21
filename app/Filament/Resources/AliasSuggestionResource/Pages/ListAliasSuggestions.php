<?php

namespace App\Filament\Resources\AliasSuggestionResource\Pages;

use App\Filament\Resources\AliasSuggestionResource;
use Filament\Resources\Pages\ListRecords;

class ListAliasSuggestions extends ListRecords
{
    protected static string $resource = AliasSuggestionResource::class;

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
