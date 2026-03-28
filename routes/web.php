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
use App\Http\Controllers\FileController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DefragHQ\DonationManagementController;
use App\Http\Controllers\AliasController;
use App\Http\Controllers\AliasReportController;
use App\Http\Controllers\DemoReportController;
use App\Http\Controllers\YoutubeController;
use App\Http\Controllers\RenderRequestController;
use App\Http\Controllers\CommunityLeaderboardController;

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
Route::get('/api/servers/live', [ServersController::class, 'apiServers'])->name('servers.api');
Route::get('/servers/json', [EndpointController::class, 'index'])->name('servers.json');

Route::get('/maps', [MapsController::class, 'index'])->name('maps');
Route::get('/maps/filters', [MapsController::class, 'filters'])->name('maps.filters');

Route::get('/maps/{mapname}/demo-matches', [MapsController::class, 'getDemoMatches'])->name('maps.demoMatches');
Route::post('/maps/{id}/flag-nsfw', [MapsController::class, 'flagNsfw'])->where('id', '[0-9]+')->middleware('auth')->name('maps.flag-nsfw');
Route::post('/maps/{id}/unflag-nsfw', [MapsController::class, 'unflagNsfw'])->where('id', '[0-9]+')->middleware('auth')->name('maps.unflag-nsfw');
Route::post('/maps/{id}/rate-difficulty', [MapsController::class, 'rateDifficulty'])->where('id', '[0-9]+')->middleware('auth')->name('maps.rate-difficulty');
Route::get('/maps/{mapname}', [MapsController::class, 'map'])->name('maps.map');

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking');

Route::get('/community', [CommunityLeaderboardController::class, 'index'])->name('community');

Route::get('/rendered-demos', [YoutubeController::class, 'index'])->name('youtube');
Route::post('/render/request', [RenderRequestController::class, 'store'])->middleware('auth')->name('render.request');
Route::post('/api/rendered-videos/{id}/report', [RenderRequestController::class, 'reportFailed'])->middleware('auth')->name('render.report');

Route::get('/records', [RecordsController::class, 'index'])->name('records');

Route::get('/downloads/{id?}/{slug?}', [BundlesController::class, 'index'])->name('bundles');

// Models routes
Route::get('/models', [ModelsController::class, 'index'])->name('models.index');
Route::get('/models/create', [ModelsController::class, 'create'])->middleware('auth')->name('models.create');
Route::post('/models', [ModelsController::class, 'store'])->middleware('auth')->name('models.store');
Route::post('/models/temp-upload', [ModelsController::class, 'tempUpload'])->middleware('auth')->name('models.tempUpload');
Route::post('/models/store-with-gifs', [ModelsController::class, 'storeWithGifs'])->middleware('auth')->name('models.storeWithGifs');
Route::post('/models/delete-temp', [ModelsController::class, 'deleteTempUpload'])->middleware('auth')->name('models.deleteTempUpload');
Route::get('/models/bulk-upload', [ModelsController::class, 'bulkUploadForm'])->middleware('auth')->name('models.bulk-upload');
Route::post('/models/bulk-upload', [ModelsController::class, 'bulkUpload'])->middleware('auth')->name('models.bulk-upload.store');
Route::get('/models/batch-generate-gifs', [ModelsController::class, 'batchGenerateGifs'])->middleware('auth')->name('models.batchGenerateGifs');
Route::get('/models/{id}/shaders', [ModelsController::class, 'getShaders'])->where('id', '[0-9]+')->name('models.shaders');
Route::get('/models/{id}/download', [ModelsController::class, 'download'])->where('id', '[0-9]+')->name('models.download');
Route::get('/models/{model}/download-extras', [ModelsController::class, 'downloadExtras'])->name('models.downloadExtras');
Route::post('/models/{id}/approve', [ModelsController::class, 'approveModel'])->where('id', '[0-9]+')->middleware('auth')->name('models.approve');
Route::post('/models/{id}/reject', [ModelsController::class, 'rejectModel'])->where('id', '[0-9]+')->middleware('auth')->name('models.reject');
Route::post('/models/{id}/flag-nsfw', [ModelsController::class, 'flagNsfw'])->where('id', '[0-9]+')->middleware('auth')->name('models.flag-nsfw');
Route::post('/models/{id}/unflag-nsfw', [ModelsController::class, 'unflagNsfw'])->where('id', '[0-9]+')->middleware('auth')->name('models.unflag-nsfw');
Route::delete('/models/{id}', [ModelsController::class, 'destroyModel'])->where('id', '[0-9]+')->middleware('auth')->name('models.destroy');
Route::get('/models/{id}', [ModelsController::class, 'show'])->where('id', '[0-9]+')->name('models.show');
Route::post('/models/{id}/save-thumbnail', [ModelsController::class, 'saveThumbnail'])->middleware('auth')->name('models.saveThumbnail');
Route::post('/models/{id}/save-head-icon', [ModelsController::class, 'saveHeadIcon'])->middleware('auth')->name('models.saveHeadIcon');
Route::post('/user/confirm-nsfw', [ModelsController::class, 'confirmNsfw'])->middleware('auth')->name('user.confirm-nsfw');
Route::post('/models/{id}/scrape-ws-metadata', [ModelsController::class, 'scrapeWsMetadata'])->middleware('auth')->where('id', '[0-9]+')->name('models.scrapeWsMetadata');
Route::post('/models/{id}/generate-still-thumbnail', [ModelsController::class, 'generateStillThumbnail'])->middleware('auth')->where('id', '[0-9]+')->name('models.generateStillThumbnail');
Route::post('/models/batch-generate-still-thumbnails', [ModelsController::class, 'batchGenerateStillThumbnails'])->middleware('auth')->name('models.batchGenerateStillThumbnails');

// Demo routes
Route::get('/demos', [DemosController::class, 'index'])->name('demos.index');
Route::get('/demos/search-uploaders', [DemosController::class, 'searchUploaders'])->name('demos.search-uploaders');
Route::get('/demos/{demo}/download', [DemosController::class, 'download'])->name('demos.download');

// Demo upload routes (requires authentication)
Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], '/demos/status', [DemosController::class, 'status'])->name('demos.status');
    Route::post('/demos/{demo}/reprocess', [DemosController::class, 'reprocess'])->name('demos.reprocess');
    Route::delete('/demos/{demo}', [DemosController::class, 'destroy'])->name('demos.destroy');

    Route::post('/demos/start-processing', [DemosController::class, 'startProcessing'])->name('demos.startProcessing');
    Route::post('/demos/reprocess-all-failed', [DemosController::class, 'reprocessAllFailed'])->name('demos.reprocessAllFailed');

    // Manual assignment routes
    Route::get('/demos/maps', [DemosController::class, 'getMaps'])->name('demos.maps');
    Route::get('/demos/maps/{mapname}/records', [DemosController::class, 'getRecords'])->name('demos.records');
    Route::post('/demos/{demo}/assign', [DemosController::class, 'assign'])->name('demos.assign');
    Route::post('/demos/{demo}/unassign', [DemosController::class, 'unassign'])->name('demos.unassign');
    Route::post('/demos/{demo}/link-youtube', [DemosController::class, 'linkYoutube'])->name('demos.link-youtube');

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

    // Alias management routes
    Route::post('/aliases', [AliasController::class, 'store'])->name('aliases.store');
    Route::delete('/aliases/{alias}', [AliasController::class, 'destroy'])->name('aliases.destroy');
    Route::post('/aliases/{alias}/report', [AliasReportController::class, 'store'])->name('aliases.report');

    // Alias suggestion routes
    Route::post('/users/{user}/suggest-alias', [App\Http\Controllers\AliasSuggestionController::class, 'store'])->name('alias-suggestions.store');
    Route::post('/alias-suggestions/{suggestion}/approve', [App\Http\Controllers\AliasSuggestionController::class, 'approve'])->name('alias-suggestions.approve');
    Route::post('/alias-suggestions/{suggestion}/reject', [App\Http\Controllers\AliasSuggestionController::class, 'reject'])->name('alias-suggestions.reject');

    // Demo reporting routes
    Route::post('/demos/{demo}/report', [DemoReportController::class, 'store'])->name('demos.report');

    // Record/demo flag routes
    Route::post('/flags', [\App\Http\Controllers\RecordFlagController::class, 'store'])->name('flags.store');
});

// Frontend error logging (works for both authenticated and anonymous users)
Route::post('/api/frontend-errors', [\App\Http\Controllers\FrontendErrorController::class, 'store']);

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


Route::get('/link-account', [SettingsController::class, 'linkAccount'])->name('link-account')->middleware('auth');

Route::post('/settings/socialmedia', [SettingsController::class, 'socialmedia'])->name('settings.socialmedia');
Route::post('/settings/preferences', [SettingsController::class, 'preferences'])->name('settings.preferences');
Route::post('/settings/mdd/generate', [SettingsController::class, 'generate'])->name('settings.mdd.generate');
Route::post('/settings/mdd/verify', [SettingsController::class, 'verify'])->name('settings.mdd.verify');
Route::post('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
Route::post('/settings/background', [SettingsController::class, 'background'])->name('settings.background');
Route::delete('/settings/background', [SettingsController::class, 'deleteBackground'])->name('settings.background.destroy');
Route::post('/settings/map-view-preferences', [SettingsController::class, 'mapViewPreferences'])->name('settings.map-view-preferences');
Route::post('/settings/physics-order', [SettingsController::class, 'physicsOrderPreferences'])->name('settings.physics-order');
Route::post('/settings/profile-layout', [SettingsController::class, 'profileLayout'])->name('settings.profile-layout');
Route::post('/settings/effects-intensity', [SettingsController::class, 'effectsIntensity'])->name('settings.effects-intensity');
Route::post('/settings/mapper-claims', [SettingsController::class, 'mapperClaims'])->middleware('auth')->name('settings.mapper-claims');
Route::get('/settings/mapper-claims', [SettingsController::class, 'getMapperClaims'])->middleware('auth')->name('settings.mapper-claims.get');
Route::post('/settings/mapper-claims/preview', [SettingsController::class, 'previewMapperClaim'])->middleware('auth')->name('settings.mapper-claims.preview');
Route::get('/settings/mapper-claims/{claimId}/maps', [SettingsController::class, 'getClaimMaps'])->middleware('auth')->name('settings.mapper-claims.maps');
Route::post('/settings/mapper-claims/{claimId}/exclusions/toggle', [SettingsController::class, 'toggleClaimExclusion'])->middleware('auth')->name('settings.mapper-claims.exclusions.toggle');
Route::post('/settings/mapper-claims/report', [SettingsController::class, 'reportMapperClaim'])->middleware('auth')->name('settings.mapper-claims.report');


Route::get('/notifications/records', [NotificationsController::class, 'records'])->middleware('auth')->name('notifications.index');
Route::post('/notifications/records', [NotificationsController::class, 'recordsclear'])->middleware('auth')->name('notifications.clear');
Route::post('/notifications/records/mark-unread', [NotificationsController::class, 'recordsMarkAllUnread'])->middleware('auth')->name('notifications.mark.unread');
Route::post('/notifications/records/{id}/toggle', [NotificationsController::class, 'recordsToggle'])->middleware('auth')->name('notifications.toggle');

Route::get('/notifications/system', [NotificationsController::class, 'system'])->middleware('auth')->name('notifications.system.index');
Route::post('/notifications/system', [NotificationsController::class, 'systemclear'])->middleware('auth')->name('notifications.system.clear');
Route::post('/notifications/system/mark-unread', [NotificationsController::class, 'systemMarkAllUnread'])->middleware('auth')->name('notifications.system.mark.unread');
Route::post('/notifications/system/{id}/toggle', [NotificationsController::class, 'systemToggle'])->middleware('auth')->name('notifications.system.toggle');

// Mapper/Creator profile API routes
Route::get('/api/profile/{userId}/mapper/stats', [\App\Http\Controllers\MapperProfileController::class, 'stats'])->name('mapper.stats');
Route::get('/api/profile/{userId}/mapper/maps', [\App\Http\Controllers\MapperProfileController::class, 'maps'])->name('mapper.maps');
Route::get('/api/profile/{userId}/mapper/top-players', [\App\Http\Controllers\MapperProfileController::class, 'topPlayers'])->name('mapper.topPlayers');
Route::get('/api/profile/{userId}/mapper/recent-activity', [\App\Http\Controllers\MapperProfileController::class, 'recentActivity'])->name('mapper.recentActivity');
Route::get('/api/profile/{userId}/mapper/heatmap', [\App\Http\Controllers\MapperProfileController::class, 'heatmap'])->name('mapper.heatmap');
Route::get('/api/profile/{userId}/mapper/highlighted-map', [\App\Http\Controllers\MapperProfileController::class, 'highlightedMap'])->name('mapper.highlightedMap');
Route::get('/api/profile/{userId}/mapper/models', [\App\Http\Controllers\MapperProfileController::class, 'models'])->name('mapper.models');
Route::post('/settings/pinned-models', [\App\Http\Controllers\MapperProfileController::class, 'savePinnedModels'])->middleware('auth')->name('settings.pinned-models');
Route::post('/settings/model-group-order', [\App\Http\Controllers\MapperProfileController::class, 'saveModelGroupOrder'])->middleware('auth')->name('settings.model-group-order');

Route::get('/profile/{userId}/progress-bar', [ProfileController::class, 'progressBar'])->name('profile.progressbar');
Route::get('/api/profile/{mddId}/activity', [ProfileController::class, 'activityData'])->name('profile.activity');
Route::get('/profile/mdd/{userId}', [ProfileController::class, 'mdd'])->name('profile.mdd');
Route::get('/profile/{userId}', [ProfileController::class, 'index'])->name('profile.index');

Route::get('/images/flags/{flag}', [WebController::class, 'flags'])->name('images.flags');
Route::get('/storage/thumbs/{image}', [WebController::class, 'thumbs'])->name('images.thumbs');

// Test Map Viewer API - MUST be before wildcard routes
Route::get('/api/test-map-data', [\App\Http\Controllers\TestMapViewerController::class, 'getMapData']);

// Case-insensitive file serving for all storage files
Route::get('/storage/{path}', [FileController::class, 'serveFile'])->where('path', '.*');

// Case-insensitive file serving for baseq3 files
Route::get('/baseq3/{path}', [FileController::class, 'serveBaseq3File'])->where('path', '.*');

Route::get('/announcements', [ChangelogController::class, 'announcements'])->name('announcements');

// Settings (overrides Jetstream's /user/profile)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/user/settings', [\Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController::class, 'show'])->name('profile.show');
    Route::redirect('/user/profile', '/user/settings', 301);
});


// Maplist routes
Route::get('/maplists', [App\Http\Controllers\MaplistController::class, 'index'])->name('maplists.index');
Route::get('/maplists/play-later', [App\Http\Controllers\MaplistController::class, 'showPlayLater'])->middleware('auth')->name('maplists.playLater');
Route::get('/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'show'])->where('id', '[0-9]+')->name('maplists.show');
Route::get('/api/maps/{mapId}/suggested-tags', [App\Http\Controllers\MaplistController::class, 'getSuggestedTagsForMap'])->name('maps.suggestedTags');

// Authenticated maplist routes
Route::middleware('auth')->group(function () {
    Route::get('/api/maplists/user', [App\Http\Controllers\MaplistController::class, 'getUserMaplists'])->name('maplists.user');
    Route::get('/api/maplists/drafts', [App\Http\Controllers\MaplistController::class, 'getDrafts'])->name('maplists.drafts');
    Route::post('/api/maplists/save-draft', [App\Http\Controllers\MaplistController::class, 'saveDraft'])->name('maplists.saveDraft');
    Route::delete('/api/maplists/draft/{id}', [App\Http\Controllers\MaplistController::class, 'deleteDraft'])->name('maplists.deleteDraft');
    Route::post('/api/maplists/create-with-maps', [App\Http\Controllers\MaplistController::class, 'createWithMaps'])->name('maplists.createWithMaps');
    Route::post('/api/maplists/{id}/reorder', [App\Http\Controllers\MaplistController::class, 'reorderMaps'])->name('maplists.reorder');
    Route::post('/api/maplists/{id}/maps', [App\Http\Controllers\MaplistController::class, 'addMap'])->name('maplists.addMap');
    Route::post('/api/maplists/{id}/like', [App\Http\Controllers\MaplistController::class, 'toggleLike'])->name('maplists.toggleLike');
    Route::post('/api/maplists/{id}/favorite', [App\Http\Controllers\MaplistController::class, 'toggleFavorite'])->name('maplists.toggleFavorite');
    Route::post('/api/maplists', [App\Http\Controllers\MaplistController::class, 'store'])->name('maplists.store');
    Route::put('/api/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'update'])->where('id', '[0-9]+')->name('maplists.update');
    Route::delete('/api/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'destroy'])->where('id', '[0-9]+')->name('maplists.destroy');
    Route::delete('/api/maplists/{maplistId}/maps/{mapId}', [App\Http\Controllers\MaplistController::class, 'removeMap'])->name('maplists.removeMap');
    Route::get('/api/maps/search', [App\Http\Controllers\MaplistController::class, 'searchMaps'])->name('maps.search');

    // Tag routes
    Route::post('/api/maps/{id}/tags', [App\Http\Controllers\TagController::class, 'addToMap'])->name('tags.addToMap');
    Route::delete('/api/maps/{mapId}/tags/{tagId}', [App\Http\Controllers\TagController::class, 'removeFromMap'])->name('tags.removeFromMap');
    Route::post('/api/maplists/{id}/tags', [App\Http\Controllers\TagController::class, 'addToMaplist'])->name('tags.addToMaplist');
    Route::delete('/api/maplists/{maplistId}/tags/{tagId}', [App\Http\Controllers\TagController::class, 'removeFromMaplist'])->name('tags.removeFromMaplist');
});

// Public tag routes
Route::get('/api/tags', [App\Http\Controllers\TagController::class, 'index'])->name('tags.index');

// Map filter profiles (lazy-loaded)
Route::get('/api/maps/profiles', [MapsController::class, 'profiles'])->name('maps.profiles');

// Donation routes
Route::get('/donations', [DonationController::class, 'index'])->name('donations.index');
Route::get('/api/donations/progress', [DonationController::class, 'getProgress'])->name('donations.progress');

// Roadmap route
Route::get('/roadmap', [WebController::class, 'roadmap'])->name('roadmap');

// Admin tools
Route::get('/admin/models-audit', [App\Http\Controllers\ModelsAuditController::class, 'index'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit');

Route::get('/admin/models-audit/download', [App\Http\Controllers\ModelsAuditController::class, 'download'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.download');

Route::post('/admin/models-audit/compare', [App\Http\Controllers\ModelsAuditController::class, 'compare'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.compare');

Route::post('/admin/models-audit/save-description', [App\Http\Controllers\ModelsAuditController::class, 'saveDescription'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.save-description');

Route::post('/admin/models-audit/build-extras-zip', [App\Http\Controllers\ModelsAuditController::class, 'buildExtrasZip'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.build-extras-zip');

Route::post('/admin/models-audit/mark-manual-review', [App\Http\Controllers\ModelsAuditController::class, 'markManualReview'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.mark-manual-review');

Route::post('/admin/models-audit/mark-failed-manual', [App\Http\Controllers\ModelsAuditController::class, 'markFailedManual'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.mark-failed-manual');

Route::get('/admin/models-audit/cached-files', [App\Http\Controllers\ModelsAuditController::class, 'cachedFiles'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.cached-files');

Route::post('/admin/models-audit/validate-local-files', [App\Http\Controllers\ModelsAuditController::class, 'validateLocalFiles'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.validate-local-files');

Route::post('/admin/models-audit/resolve', [App\Http\Controllers\ModelsAuditController::class, 'resolve'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.resolve');

Route::post('/admin/models-audit/import', [App\Http\Controllers\ModelsAuditController::class, 'importModel'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.import');

Route::post('/admin/models-audit/import-pk3', [App\Http\Controllers\ModelsAuditController::class, 'importPk3'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.import-pk3');

Route::get('/admin/models-audit/ws-detail-check', [App\Http\Controllers\ModelsAuditController::class, 'wsDetailCheck'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.ws-detail-check');

Route::get('/admin/models-audit/missing-skins-in-name', [App\Http\Controllers\ModelsAuditController::class, 'missingSkinsInName'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.missing-skins-in-name');

Route::post('/admin/models-audit/fix-model-name', [App\Http\Controllers\ModelsAuditController::class, 'fixModelName'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.fix-model-name');

// PayPal webhook (no CSRF protection needed for webhooks)
Route::post('/api/paypal/webhook', [\App\Http\Controllers\PayPalWebhookController::class, 'handleWebhook'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// DefragHQ Admin - Donation Management (requires authentication)
// Note: All donation management is now handled by Filament admin panel
// Route::middleware('auth')->prefix('defraghq')->group(function () {
//     Route::post('/donations', [DonationManagementController::class, 'storeDonation'])->name('defraghq.donations.store');
//     Route::put('/donations/{donation}', [DonationManagementController::class, 'updateDonation'])->name('defraghq.donations.update');
//     Route::delete('/donations/{donation}', [DonationManagementController::class, 'deleteDonation'])->name('defraghq.donations.delete');
//
//     Route::post('/self-raised', [DonationManagementController::class, 'storeSelfRaised'])->name('defraghq.selfraised.store');
//     Route::put('/self-raised/{selfRaised}', [DonationManagementController::class, 'updateSelfRaised'])->name('defraghq.selfraised.update');
//     Route::delete('/self-raised/{selfRaised}', [DonationManagementController::class, 'deleteSelfRaised'])->name('defraghq.selfraised.delete');
//
//     Route::post('/donation-goal', [DonationManagementController::class, 'updateGoal'])->name('defraghq.goal.update');
// });

// Clean URLs for admin-created pages (catch-all, must be last route)
Route::get("/{slug}", [App\Http\Controllers\PagesController::class, "index"])->name("pages.show.clean")->where("slug", "[a-z0-9\\-]+");
