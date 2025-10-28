<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Navigation\NavigationItem;

class Donations extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Donations';

    protected static string $view = 'filament.pages.donations';

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Donations')
                ->url('/defraghq/donations')
                ->icon('heroicon-o-heart')
                ->sort(15),
        ];
    }
}
