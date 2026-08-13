<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\SftpCredential;
use App\Models\UploadedDemo;
use App\Observers\SftpCredentialObserver;
use App\Observers\UploadedDemoCompsObserver;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('q3-stylesheet', __DIR__ . '/../../resources/css/items.css'),
        ]);

        SftpCredential::observe(SftpCredentialObserver::class);

        // Settles comps entries once the parser has read the demo they
        // were uploaded from - see UploadedDemoCompsObserver.
        UploadedDemo::observe(UploadedDemoCompsObserver::class);
    }
}
