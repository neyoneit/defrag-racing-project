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

// Desktop launcher API. Sanctum personal access tokens issued at
// /user/launcher-tokens with abilities ["launcher:upload","launcher:read"].
// Split by both ability AND throttle bucket so cheap reads can't get
// pushed into 429 by the same per-token limit that gates expensive
// uploads:
//
//  - launcher-read (1200/min): lookup-by-hash, servers, notifications.
//    A rescan fires one lookup-by-hash per demo so on first run a
//    long-time player with thousands of cached demos hits it hard.
//    20/sec headroom keeps that working even on a "no CPU limit" setting.
//
//  - launcher-upload (300/min): upload-demo only. Multipart upload
//    + ProcessDemoJob dispatch. 7 workers at ~1-2s/job give us
//    ~210-420 jobs/min sustained, so 5/sec per token absorbs first-
//    run rescan bursts (1000 demos in ~3 min) while leaving headroom
//    when several uploaders run at once. Pending jobs queue and
//    drain at worker rate.
//
// `log.api` writes every call to api_call_log for the same audit trail
// the post-incident /api lockdown added, applied here proactively.
Route::prefix('launcher')
    ->middleware(['auth:sanctum', 'log.api'])
    ->group(function () {
        Route::middleware(['abilities:launcher:upload', 'throttle:launcher-upload'])->group(function () {
            Route::post('/upload-demo', [\App\Http\Controllers\Api\LauncherController::class, 'uploadDemo']);
        });

        // lookup-by-hash is a write-ability route (it's POST and lives
        // alongside upload-demo conceptually) but uses the read bucket
        // because it's a single indexed SELECT - same cost shape as
        // /servers and /notifications.
        Route::middleware(['abilities:launcher:upload', 'throttle:launcher-read'])->group(function () {
            Route::post('/lookup-by-hash', [\App\Http\Controllers\Api\LauncherController::class, 'lookupByHash']);
        });

        Route::middleware(['abilities:launcher:read', 'throttle:launcher-read'])->group(function () {
            Route::get('/servers', [\App\Http\Controllers\Api\LauncherController::class, 'servers']);
            Route::get('/notifications', [\App\Http\Controllers\Api\LauncherController::class, 'notifications']);
        });
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
    Route::get('/discord-reprocess-single-message', [\App\Http\Controllers\Api\DemomeController::class, 'discordReprocessSingleMessage']);
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
