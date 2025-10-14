<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\MapsController;
use App\Http\Controllers\BundlesController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServersController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\EndpointController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\DemosController;
use App\Http\Controllers\ModelsController;
use App\Http\Controllers\WikiController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [WebController::class, 'home'])->name('home');
Route::get('/getting-started', [WebController::class, 'gettingstarted'])->name('getting.started');


Route::post('/search', [SearchController::class, 'search'])->name('search');

Route::get('/servers', [ServersController::class, 'index'])->name('servers');
Route::get('/servers/json', [EndpointController::class, 'index'])->name('servers.json');

Route::get('/maps', [MapsController::class, 'index'])->name('maps');
Route::get('/maps/filters', [MapsController::class, 'filters'])->name('maps.filters');

Route::get('/maps/{mapname}', [MapsController::class, 'map'])->name('maps.map');

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking');

Route::get('/records', [RecordsController::class, 'index'])->name('records');

Route::get('/bundles/{id?}/{slug?}', [BundlesController::class, 'index'])->name('bundles');

// Models routes
Route::get('/models', [ModelsController::class, 'index'])->name('models.index');
Route::get('/models/create', [ModelsController::class, 'create'])->middleware('auth')->name('models.create');
Route::post('/models', [ModelsController::class, 'store'])->middleware('auth')->name('models.store');
Route::get('/models/bulk-upload', [ModelsController::class, 'bulkUploadForm'])->middleware('auth')->name('models.bulk-upload');
Route::post('/models/bulk-upload', [ModelsController::class, 'bulkUpload'])->middleware('auth')->name('models.bulk-upload.store');
Route::get('/models/{id}', [ModelsController::class, 'show'])->name('models.show');
Route::get('/models/{id}/download', [ModelsController::class, 'download'])->name('models.download');
Route::post('/models/{id}/generate-thumbnail', [ModelsController::class, 'generateThumbnail'])->middleware('auth')->name('models.generateThumbnail');
Route::post('/models/{id}/save-thumbnail', [ModelsController::class, 'saveThumbnail'])->middleware('auth')->name('models.saveThumbnail');
Route::post('/models/{id}/save-head-icon', [ModelsController::class, 'saveHeadIcon'])->middleware('auth')->name('models.saveHeadIcon');

// Demo routes
Route::get('/demos', [DemosController::class, 'index'])->name('demos.index');
Route::get('/demos/{demo}/download', [DemosController::class, 'download'])->name('demos.download');

// Demo upload routes (requires authentication)
Route::middleware('auth')->group(function () {
    Route::get('/demos/status', [DemosController::class, 'status'])->name('demos.status');
    Route::post('/demos/{demo}/reprocess', [DemosController::class, 'reprocess'])->name('demos.reprocess');
    Route::delete('/demos/{demo}', [DemosController::class, 'destroy'])->name('demos.destroy');

    Route::get('/demos/status', [DemosController::class, 'status'])->name('demos.status');
    Route::post('/demos/{demo}/reprocess', [DemosController::class, 'reprocess'])->name('demos.reprocess');
    Route::delete('/demos/{demo}', [DemosController::class, 'destroy'])->name('demos.destroy');

    // Manual assignment routes
    Route::get('/demos/maps', [DemosController::class, 'getMaps'])->name('demos.maps');
    Route::get('/demos/maps/{mapname}/records', [DemosController::class, 'getRecords'])->name('demos.records');
    Route::post('/demos/{demo}/assign', [DemosController::class, 'assign'])->name('demos.assign');
    Route::post('/demos/{demo}/unassign', [DemosController::class, 'unassign'])->name('demos.unassign');

    // OAuth routes
    Route::get('/oauth/discord', [App\Http\Controllers\OAuthController::class, 'redirectToDiscord'])->name('oauth.discord');
    Route::get('/oauth/discord/callback', [App\Http\Controllers\OAuthController::class, 'handleDiscordCallback'])->name('oauth.discord.callback');
    Route::post('/oauth/discord/disconnect', [App\Http\Controllers\OAuthController::class, 'disconnectDiscord'])->name('oauth.discord.disconnect');

    Route::get('/oauth/twitch', [App\Http\Controllers\OAuthController::class, 'redirectToTwitch'])->name('oauth.twitch');
    Route::get('/oauth/twitch/callback', [App\Http\Controllers\OAuthController::class, 'handleTwitchCallback'])->name('oauth.twitch.callback');
    Route::post('/oauth/twitch/disconnect', [App\Http\Controllers\OAuthController::class, 'disconnectTwitch'])->name('oauth.twitch.disconnect');

    Route::get('/oauth/steam', [App\Http\Controllers\OAuthController::class, 'redirectToSteam'])->name('oauth.steam');
    Route::get('/oauth/steam/callback', [App\Http\Controllers\OAuthController::class, 'handleSteamCallback'])->name('oauth.steam.callback');
    Route::post('/oauth/steam/disconnect', [App\Http\Controllers\OAuthController::class, 'disconnectSteam'])->name('oauth.steam.disconnect');

    Route::get('/oauth/twitter', [App\Http\Controllers\OAuthController::class, 'redirectToTwitter'])->name('oauth.twitter');
    Route::get('/oauth/twitter/callback', [App\Http\Controllers\OAuthController::class, 'handleTwitterCallback'])->name('oauth.twitter.callback');
    Route::post('/oauth/twitter/disconnect', [App\Http\Controllers\OAuthController::class, 'disconnectTwitter'])->name('oauth.twitter.disconnect');
});

// Make the main upload endpoint publicly reachable so the demos page can accept
// anonymous uploads directly. The front-end posts to route('demos.upload') so
// keeping the same route name preserves UI behavior.
Route::post('/demos/upload', [DemosController::class, 'upload'])->name('demos.upload');

    // Local-only debug routes (outside auth group so you can curl them easily during development)
    if (app()->environment('local')) {
        // Simple GET form for manual testing (safer path to avoid wildcard collisions)
        Route::get('/demos/debug/detect', function () {
            if (!app()->environment('local')) abort(404);
            return <<<'HTML'
    <html><body>
    <h3>Debug Upload Detection</h3>
    <form method="post" enctype="multipart/form-data" action="/demos/debug/detect">
        <input type="file" name="file" />
        <button type="submit">Upload</button>
        <input type="hidden" name="_token" value="" />
    </form>
    <p>Use curl: curl -F "file=@/path/to/demos.zip" -X POST http://localhost/demos/debug/detect</p>
    </body></html>
    HTML;
        });

        // POST route for debug detection. We intentionally allow this to be hit without auth
        // or CSRF in local environment for easy debugging via curl.
        Route::post('/demos/debug/detect', [\App\Http\Controllers\DemosController::class, 'debugDetect'])
            ->name('demos.debugDetect');

        // POST route for debug upload. Allows easy CURL testing of archives in local env.
        Route::post('/demos/debug/upload', [\App\Http\Controllers\DemosController::class, 'debugUpload'])
            ->name('demos.debugUpload');
    }

        // Local helper to inspect session token and headers for debugging CSRF
        Route::get('/_debug/session', function (\Illuminate\Http\Request $request) {
            if (!app()->environment('local')) abort(404);
            return response()->json([
                'session_token' => $request->session()->token(),
                'headers' => $request->headers->all(),
                'cookies' => $request->cookies->all(),
            ]);
        });


Route::post('/settings/socialmedia', [SettingsController::class, 'socialmedia'])->name('settings.socialmedia');
Route::post('/settings/preferences', [SettingsController::class, 'preferences'])->name('settings.preferences');
Route::post('/settings/mdd/generate', [SettingsController::class, 'generate'])->name('settings.mdd.generate');
Route::post('/settings/mdd/verify', [SettingsController::class, 'verify'])->name('settings.mdd.verify');
Route::post('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
Route::post('/settings/background', [SettingsController::class, 'background'])->name('settings.background');
Route::delete('/settings/background', [SettingsController::class, 'deleteBackground'])->name('settings.background.destroy');


Route::get('/notifications/records', [NotificationsController::class, 'records'])->middleware('auth')->name('notifications.index');
Route::post('/notifications/records', [NotificationsController::class, 'recordsclear'])->middleware('auth')->name('notifications.clear');

Route::get('/notifications/system', [NotificationsController::class, 'system'])->middleware('auth')->name('notifications.system.index');
Route::post('/notifications/system', [NotificationsController::class, 'systemclear'])->middleware('auth')->name('notifications.system.clear');

Route::get('/profile/{userId}/progress-bar', [ProfileController::class, 'progressBar'])->name('profile.progressbar');
Route::get('/profile/mdd/{userId}', [ProfileController::class, 'mdd'])->name('profile.mdd');
Route::get('/profile/{userId}', [ProfileController::class, 'index'])->name('profile.index');

Route::get('/images/flags/{flag}', [WebController::class, 'flags'])->name('images.flags');
Route::get('/storage/thumbs/{image}', [WebController::class, 'thumbs'])->name('images.thumbs');

Route::get('/announcements', [ChangelogController::class, 'announcements'])->name('announcements');

Route::get('/privacy-policy', [PagesController::class, 'privacypolicy'])->name('pages.privacy-policy');
Route::get('/privacy-policy-twitch', [PagesController::class, 'privacypolicytwitch'])->name('pages.privacy-policy-twitch');
Route::get('/pages/{slug}', [PagesController::class, 'index'])->name('pages.show');

// Maplist routes
Route::get('/maplists', [App\Http\Controllers\MaplistController::class, 'index'])->name('maplists.index');
Route::get('/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'show'])->name('maplists.show');

// Authenticated maplist routes
Route::middleware('auth')->group(function () {
    Route::get('/api/maplists/user', [App\Http\Controllers\MaplistController::class, 'getUserMaplists'])->name('maplists.user');
    Route::post('/api/maplists', [App\Http\Controllers\MaplistController::class, 'store'])->name('maplists.store');
    Route::put('/api/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'update'])->name('maplists.update');
    Route::delete('/api/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'destroy'])->name('maplists.destroy');
    Route::post('/api/maplists/{id}/maps', [App\Http\Controllers\MaplistController::class, 'addMap'])->name('maplists.addMap');
    Route::delete('/api/maplists/{maplistId}/maps/{mapId}', [App\Http\Controllers\MaplistController::class, 'removeMap'])->name('maplists.removeMap');
    Route::post('/api/maplists/{id}/like', [App\Http\Controllers\MaplistController::class, 'toggleLike'])->name('maplists.toggleLike');
    Route::post('/api/maplists/{id}/favorite', [App\Http\Controllers\MaplistController::class, 'toggleFavorite'])->name('maplists.toggleFavorite');
    Route::get('/api/maps/search', [App\Http\Controllers\MaplistController::class, 'searchMaps'])->name('maps.search');
});

// Wiki proxy routes - must be at the end to avoid conflicts
Route::any('/wiki/{path?}', [WikiController::class, 'proxy'])->where('path', '.*')->name('wiki');
