<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Desktop launcher API. Sanctum personal access token with ability
// "launcher:upload"; users generate tokens at /user/launcher-tokens.
// Throttle key is the token id so two launchers on different PCs with the
// same user account each get their own bucket.
Route::prefix('launcher')
    ->middleware(['auth:sanctum', 'abilities:launcher:upload', 'throttle:launcher'])
    ->group(function () {
        Route::post('/lookup-by-hash', [\App\Http\Controllers\Api\LauncherController::class, 'lookupByHash']);
        Route::post('/upload-demo', [\App\Http\Controllers\Api\LauncherController::class, 'uploadDemo']);
    });

// Profile + records AJAX endpoints used by the SPA frontend and (optionally)
// by external clients via personal API tokens. `auth:sanctum,web` accepts
// either a Bearer token in the Authorization header OR a session cookie
// from a logged-in browser. Anonymous requests get a 401 — the frontend
// hides the matching panels client-side so anon profile pages stay clean.
//
// Per-user rate-limit kicks in automatically because `throttle:api` keys
// by user_id when authenticated.
Route::middleware(['auth:sanctum,web', 'log.api'])->group(function () {
    Route::get('/profile/{mddId}/extras', [\App\Http\Controllers\ProfileController::class, 'profileExtras']);
    Route::get('/search-players', [\App\Http\Controllers\ProfileController::class, 'searchPlayers']);
    Route::get('/profile/{userId}/compare/{rivalId}', [\App\Http\Controllers\ProfileController::class, 'comparePlayer']);
    Route::get('/records/search', [\App\Http\Controllers\RecordsController::class, 'search']);
});

// Demome renderer API
Route::prefix('demome')->middleware('demome.token')->withoutMiddleware('throttle:api')->group(function () {
    Route::get('/queue', [\App\Http\Controllers\Api\DemomeController::class, 'queue']);
    Route::post('/claim/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'claim']);
    Route::post('/complete/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'complete']);
    Route::post('/fail/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'fail']);
    Route::post('/reset/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'resetToPending']);
    Route::post('/heartbeat', [\App\Http\Controllers\Api\DemomeController::class, 'heartbeat']);
    Route::post('/report', [\App\Http\Controllers\Api\DemomeController::class, 'report']);
    Route::post('/report-by-hash', [\App\Http\Controllers\Api\DemomeController::class, 'reportByHash']);
    Route::post('/start-discord-render', [\App\Http\Controllers\Api\DemomeController::class, 'startDiscordRender']);
    Route::get('/discord-restart-marker', [\App\Http\Controllers\Api\DemomeController::class, 'discordRestartMarker']);
    Route::post('/upload-demo', [\App\Http\Controllers\Api\DemomeController::class, 'uploadDemo']);
    Route::get('/download-demo/{demo}', [\App\Http\Controllers\Api\DemomeController::class, 'downloadDemo']);
    Route::get('/videos-to-publish', [\App\Http\Controllers\Api\DemomeController::class, 'videosToPublish']);
    Route::post('/mark-published/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'markPublished']);
    Route::get('/lookup-by-hash/{hash}', [\App\Http\Controllers\Api\DemomeController::class, 'lookupByHash']);
    Route::post('/swap-video/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'swapVideo']);
    Route::get('/upload-counts-today', [\App\Http\Controllers\Api\DemomeController::class, 'uploadCountsToday']);
    Route::get('/publish-counts-today', [\App\Http\Controllers\Api\DemomeController::class, 'publishCountsToday']);
    Route::post('/auto-approve-publish', [\App\Http\Controllers\Api\DemomeController::class, 'autoApprovePublish']);
    Route::get('/video-metadata/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'videoMetadata']);
    Route::get('/videos-needing-metadata-update', [\App\Http\Controllers\Api\DemomeController::class, 'videosNeedingMetadataUpdate']);
});
