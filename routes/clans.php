<?php

use App\Http\Controllers\Clans\ClansController;
use App\Http\Controllers\Clans\ManageClanController;

Route::get('/', [ClansController::class, 'index'])->name('clans.index');
Route::get('/{clan}', [ClansController::class, 'show'])->name('clans.show');

Route::post('/invitations/{invitation}/accept', [ClansController::class, 'accept'])->name('clans.invitation.accept');
Route::post('/invitations/{invitation}/reject', [ClansController::class, 'reject'])->name('clans.invitation.reject');

Route::prefix('manage')->middleware('auth')->group(function () {
    Route::get('/create', [ManageClanController::class, 'create'])->name('clans.manage.create');
    Route::post('/create', [ManageClanController::class, 'store'])->name('clans.manage.store');

    Route::post('/edit/{clan}', [ManageClanController::class, 'update'])->name('clans.manage.update');

    Route::post('/invite', [ManageClanController::class, 'invite'])->name('clans.manage.invite');
    Route::post('/kick', [ManageClanController::class, 'kick'])->name('clans.manage.kick');
    Route::post('/leave', [ManageClanController::class, 'leave'])->name('clans.manage.leave');
    Route::post('/transfer', [ManageClanController::class, 'transfer'])->name('clans.manage.transfer');
    Route::post('/dismantle', [ManageClanController::class, 'dismantle'])->name('clans.manage.dismantle');

    Route::post('/{clan}/member/{user}/note', [ManageClanController::class, 'updateMemberNote'])->name('clans.manage.member.note');
    Route::delete('/{clan}/member/{user}/config', [ManageClanController::class, 'deleteMemberConfig'])->name('clans.manage.member.config.delete');
});
