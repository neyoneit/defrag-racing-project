<?php

use App\Http\Controllers\HeadhunterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    // Public routes
    Route::get('/headhunter', [HeadhunterController::class, 'index'])->name('headhunter.index');
    Route::get('/headhunter/{challenge}', [HeadhunterController::class, 'show'])->name('headhunter.show');

    // Authenticated routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/headhunter/create/new', [HeadhunterController::class, 'create'])->name('headhunter.create');
        Route::post('/headhunter', [HeadhunterController::class, 'store'])->name('headhunter.store');
        Route::post('/headhunter/{challenge}/participate', [HeadhunterController::class, 'participate'])->name('headhunter.participate');
        Route::post('/headhunter/{challenge}/submit-proof', [HeadhunterController::class, 'submitProof'])->name('headhunter.submitProof');
    });
});
