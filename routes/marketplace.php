<?php

use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MarketplaceSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    // Public routes
    Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::get('/marketplace/creators', [MarketplaceController::class, 'creators'])->name('marketplace.creators');
    Route::get('/marketplace/creators/{user}', [MarketplaceController::class, 'creatorProfile'])->name('marketplace.creator');
    Route::get('/marketplace/{listing}', [MarketplaceController::class, 'show'])->name('marketplace.show');

    // Authenticated routes (verified required)
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/marketplace/listing/create', [MarketplaceController::class, 'createListing'])->name('marketplace.create');
        Route::post('/marketplace/listing', [MarketplaceController::class, 'storeListing'])->name('marketplace.store');
        Route::post('/marketplace/{listing}/status', [MarketplaceController::class, 'updateListingStatus'])->name('marketplace.status');
        Route::post('/marketplace/{listing}/assign', [MarketplaceController::class, 'assignListing'])->name('marketplace.assign');
        Route::post('/marketplace/{listing}/review', [MarketplaceController::class, 'storeReview'])->name('marketplace.review');

        // Creator profile settings
        Route::get('/settings/creator-profile', [MarketplaceSettingsController::class, 'getCreatorProfile'])->name('settings.creator-profile.get');
        Route::post('/settings/creator-profile', [MarketplaceSettingsController::class, 'updateCreatorProfile'])->name('settings.creator-profile');
        Route::get('/settings/creator-profile/search-maps', [MarketplaceSettingsController::class, 'searchMapsForFeatured'])->name('settings.creator-profile.search-maps');
    });
});
