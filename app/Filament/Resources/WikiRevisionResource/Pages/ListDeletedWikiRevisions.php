<?php

namespace App\Filament\Resources\WikiRevisionResource\Pages;

use App\Filament\Resources\WikiRevisionResource;
use Filament\Resources\Pages\ListRecords;

class ListDeletedWikiRevisions extends ListRecords
{
    protected static string $resource = WikiRevisionResource::class;
}
