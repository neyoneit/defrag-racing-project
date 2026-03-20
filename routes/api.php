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

Route::get('/profile/{userId}/beatable-records/{rivalMddId}', [\App\Http\Controllers\ProfileController::class, 'beatableRecordsApi']);
Route::get('/profile/{mddId}/extras', [\App\Http\Controllers\ProfileController::class, 'profileExtras']);
Route::get('/search-players', [\App\Http\Controllers\ProfileController::class, 'searchPlayers']);
Route::get('/profile/{userId}/compare/{rivalId}', [\App\Http\Controllers\ProfileController::class, 'comparePlayer']);
Route::get('/records/search', [\App\Http\Controllers\RecordsController::class, 'search']);

// Demome renderer API
Route::prefix('demome')->middleware('demome.token')->group(function () {
    Route::get('/queue', [\App\Http\Controllers\Api\DemomeController::class, 'queue']);
    Route::post('/claim/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'claim']);
    Route::post('/complete/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'complete']);
    Route::post('/fail/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'fail']);
    Route::post('/heartbeat', [\App\Http\Controllers\Api\DemomeController::class, 'heartbeat']);
    Route::post('/report', [\App\Http\Controllers\Api\DemomeController::class, 'report']);
    Route::post('/upload-demo', [\App\Http\Controllers\Api\DemomeController::class, 'uploadDemo']);
    Route::get('/download-demo/{demo}', [\App\Http\Controllers\Api\DemomeController::class, 'downloadDemo']);
    Route::get('/videos-to-publish', [\App\Http\Controllers\Api\DemomeController::class, 'videosToPublish']);
    Route::post('/mark-published/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'markPublished']);
    Route::get('/lookup-by-hash/{hash}', [\App\Http\Controllers\Api\DemomeController::class, 'lookupByHash']);
});
