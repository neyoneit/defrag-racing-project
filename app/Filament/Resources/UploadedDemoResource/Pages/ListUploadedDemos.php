<?php

namespace App\Filament\Resources\UploadedDemoResource\Pages;

use App\Filament\Resources\UploadedDemoResource;
use Filament\Resources\Pages\ListRecords;

class ListUploadedDemos extends ListRecords
{
    protected static string $resource = UploadedDemoResource::class;
}
