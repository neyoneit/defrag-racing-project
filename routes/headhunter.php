<?php

use App\Http\Controllers\HeadhunterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    // Public routes
    Route::get('/headhunter', [HeadhunterController::class, 'index'])->name('headhunter.index');
    Route::get('/headhunter/{challenge}', [HeadhunterController::class, 'show'])->name('headhunter.show');

    // Authenticated routes (verified required)
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/headhunter/create/new', [HeadhunterController::class, 'create'])->name('headhunter.create');
        Route::post('/headhunter', [HeadhunterController::class, 'store'])->name('headhunter.store');
        Route::put('/headhunter/{challenge}', [HeadhunterController::class, 'updateChallenge'])->name('headhunter.update');
        Route::post('/headhunter/{challenge}/participate', [HeadhunterController::class, 'participate'])->name('headhunter.participate');
        Route::post('/headhunter/{challenge}/submit-proof', [HeadhunterController::class, 'submitProof'])->name('headhunter.submitProof');
        Route::post('/headhunter/{challenge}/approve/{participant}', [HeadhunterController::class, 'approveSubmission'])->name('headhunter.approve');
        Route::post('/headhunter/{challenge}/reject/{participant}', [HeadhunterController::class, 'rejectSubmission'])->name('headhunter.reject');
        Route::post('/headhunter/{challenge}/close', [HeadhunterController::class, 'closeChallenge'])->name('headhunter.close');
        Route::post('/headhunter/{challenge}/request-edit', [HeadhunterController::class, 'requestEdit'])->name('headhunter.requestEdit');
        Route::post('/headhunter/{challenge}/dispute', [HeadhunterController::class, 'createDispute'])->name('headhunter.dispute');
        Route::post('/headhunter/{challenge}/unapprove/{participant}', [HeadhunterController::class, 'unapproveSubmission'])->name('headhunter.unapprove');
        Route::delete('/headhunter/{challenge}/remove-participant/{participant}', [HeadhunterController::class, 'removeParticipant'])->name('headhunter.removeParticipant');
        Route::post('/headhunter/{challenge}/dispute/{dispute}/respond', [HeadhunterController::class, 'respondToDispute'])->name('headhunter.respondDispute');
    });
});
