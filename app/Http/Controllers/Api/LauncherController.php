<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDemoJob;
use App\Models\Map;
use App\Models\Notification;
use App\Models\Record;
use App\Models\RecordNotification;
use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use App\Services\ServerListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints consumed by the desktop launcher (tauri app). All routes sit
 * behind a Sanctum personal access token issued at /user/launcher-tokens.
 * Two ability scopes: launcher:upload (write — demo uploads) and
 * launcher:read (server browser + notifications feed). Every request
 * carries `Authorization: Bearer <token>`.
 */
class LauncherController extends Controller
{
    /**
     * Given an MD5 hash of a demo file, tell the launcher whether it already
     * exists on the server. Prevents re-uploading demos that were previously
     * backed up (e.g. after a reinstall) or were uploaded through the web.
     *
     * Expected body:   { "hash": "<32-char md5>" }
     * Response 200:    { "exists": bool, "demo_id": int|null }
     */
    public function lookupByHash(Request $request)
    {
        $data = $request->validate([
            'hash' => 'required|string|size:32',
        ]);

        $demo = UploadedDemo::where('file_hash', strtolower($data['hash']))
            ->first(['id']);

        return response()->json([
            'exists' => $demo !== null,
            'demo_id' => $demo?->id,
        ]);
    }

    /**
     * Single-file demo upload. The launcher calls /lookup-by-hash first, so
     * duplicates are the exception, not the rule — we still defend against
     * them because two launchers on different PCs could race on the same
     * demo.
     *
     * Expected multipart body:
     *   demo     (file, .dm_68/.dm_69 etc.)
     *   hash     (optional md5; if omitted we compute it server-side)
     *
     * Response 200: { "demo_id": int, "status": "uploaded" }
     * Response 409: { "error": "duplicate", "demo_id": int }
     */
    public function uploadDemo(Request $request)
    {
        $user = $request->user();

        if (! $user->canUploadDemos()) {
            return response()->json([
                'error' => 'Your account has been restricted from uploading demos.',
            ], 403);
        }

        $request->validate([
            'demo' => 'required|file|max:512000', // 512 MB, same cap as the web form
            'hash' => 'nullable|string|size:32',
        ]);

        $file = $request->file('demo');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        if (! preg_match('/^dm_\d+$/', $extension)) {
            return response()->json([
                'error' => 'Invalid demo file extension (expected dm_68/dm_69/...).',
            ], 422);
        }

        $hash = strtolower($request->input('hash') ?: md5_file($file->getPathname()));

        // Short-circuit: if the hash is already in the DB, treat as duplicate.
        // Returning 409 so the launcher can skip + mark local demo as "already backed up".
        $existing = UploadedDemo::where('file_hash', $hash)->first(['id', 'status']);
        if ($existing) {
            return response()->json([
                'error' => 'duplicate',
                'demo_id' => $existing->id,
                'status' => $existing->status,
            ], 409);
        }

        $demo = UploadedDemo::create([
            'original_filename' => $originalName,
            'file_path' => '',
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'user_id' => $user->id,
            'status' => 'uploaded',
        ]);

        $directory = storage_path("app/demos/temp/{$demo->id}");
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $file->move($directory, $originalName);
        $demo->update(['file_path' => "demos/temp/{$demo->id}/{$originalName}"]);

        ProcessDemoJob::dispatch($demo);

        Log::info('Launcher demo upload', [
            'user_id' => $user->id,
            'demo_id' => $demo->id,
            'hash' => $hash,
            'size' => $demo->file_size,
        ]);

        return response()->json([
            'demo_id' => $demo->id,
            'status' => 'uploaded',
        ]);
    }

    /**
     * Server browser feed. Same payload the web /servers page consumes via
     * /api/servers/live — per-user mytime / myrank fields are populated
     * for the token's owner, so the launcher can show "your PB on this
     * map" the same way the website does. mapdata.thumbnail is already
     * included by the shared service.
     *
     * Response 200: [ { id, name, ip, port, map, mapdata: {thumbnail, ...},
     *                   onlinePlayers: [...], mytime_time, myrank_position,
     *                   besttime_*, ... } ]
     */
    public function servers(Request $request, ServerListService $servers)
    {
        return response()->json([
            'servers' => $servers->list($request),
        ]);
    }

    /**
     * Notifications feed: record-related (PB beaten, WR taken) plus
     * system (alias suggestions, demome events, etc.). Mirrors the web
     * notification center, filtered to the authenticated user via the
     * `user_id` column on both tables. Page size and order match the
     * web's NotificationsController so the launcher can poll without
     * surprising the user with a different ordering.
     */
    public function notifications(Request $request)
    {
        $userId = $request->user()->id;

        $records = RecordNotification::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get();

        $system = Notification::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get();

        $unreadRecords = RecordNotification::where('user_id', $userId)
            ->where('read', false)
            ->count();

        $unreadSystem = Notification::where('user_id', $userId)
            ->where('read', false)
            ->count();

        return response()->json([
            'records' => $records,
            'system' => $system,
            'unread' => [
                'records' => $unreadRecords,
                'system' => $unreadSystem,
                'total' => $unreadRecords + $unreadSystem,
            ],
        ]);
    }

    /**
     * Per-row toggle for a record notification. Flips `read` and
     * returns the new state + fresh unread counts so the launcher
     * can update its badge without a separate /notifications poll.
     * 404 when the row doesn't exist OR belongs to a different user
     * (the where on user_id is what enforces tenant isolation).
     */
    public function notificationRecordToggle(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $row = RecordNotification::where('user_id', $userId)->where('id', $id)->first();
        if (! $row) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $row->read = ! $row->read;
        $row->save();
        return response()->json([
            'id' => $row->id,
            'read' => (bool) $row->read,
            'unread' => $this->unreadCounts($userId),
        ]);
    }

    /** Mark every record notification for this user as read. */
    public function notificationRecordsMarkRead(Request $request)
    {
        $userId = $request->user()->id;
        RecordNotification::where('user_id', $userId)->where('read', false)->update(['read' => true]);
        return response()->json(['unread' => $this->unreadCounts($userId)]);
    }

    /** Mark every record notification for this user as unread. */
    public function notificationRecordsMarkUnread(Request $request)
    {
        $userId = $request->user()->id;
        RecordNotification::where('user_id', $userId)->where('read', true)->update(['read' => false]);
        return response()->json(['unread' => $this->unreadCounts($userId)]);
    }

    /** System notification equivalents. Same shape as the record ones. */
    public function notificationSystemToggle(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $row = Notification::where('user_id', $userId)->where('id', $id)->first();
        if (! $row) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $row->read = ! $row->read;
        $row->save();
        return response()->json([
            'id' => $row->id,
            'read' => (bool) $row->read,
            'unread' => $this->unreadCounts($userId),
        ]);
    }

    public function notificationSystemMarkRead(Request $request)
    {
        $userId = $request->user()->id;
        Notification::where('user_id', $userId)->where('read', false)->update(['read' => true]);
        return response()->json(['unread' => $this->unreadCounts($userId)]);
    }

    public function notificationSystemMarkUnread(Request $request)
    {
        $userId = $request->user()->id;
        Notification::where('user_id', $userId)->where('read', true)->update(['read' => false]);
        return response()->json(['unread' => $this->unreadCounts($userId)]);
    }

    /**
     * Shared unread-count payload returned by every mark-read/unread
     * mutation above. Keeps the launcher bell badge in sync with what
     * the server just changed, without a separate /notifications
     * round-trip.
     */
    private function unreadCounts(int $userId): array
    {
        $records = RecordNotification::where('user_id', $userId)->where('read', false)->count();
        $system = Notification::where('user_id', $userId)->where('read', false)->count();
        return [
            'records' => $records,
            'system' => $system,
            'total' => $records + $system,
        ];
    }

    /**
     * Minimal "who am I" for the launcher. Returns the fields the
     * launcher needs to wire up the top nav's Profile button (mdd_id
     * for the /profile/{id} link, name + country for the badge) and
     * nothing else. Used once per app start; cached in the launcher's
     * config store so the button works offline thereafter.
     *
     * Lives under the launcher-read bucket so a misclick on the
     * Profile button can't be turned into a 429 by the global
     * throttle:api ceiling.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'mdd_id' => $user->mdd_id,
            'name' => $user->name,
            'plain_name' => $user->plain_name ?? null,
            'country' => $user->country ?? null,
        ]);
    }

    /**
     * Paginated recent records for the launcher's Records tab. Single
     * physics per call (the launcher renders two tables side-by-side
     * and queries each independently) so the response stays small and
     * the per-page count is honest.
     *
     * Deliberately a minimal projection of what RecordsController
     * returns to the web Inertia page - no PlayerMapScore enrichment,
     * no rating multipliers, no offline_records merge. The launcher
     * lists "newest records, by physics" as a quick browser; users
     * who want the rich rating context click through to the web map
     * page anyway.
     *
     * Query: ?physics=vq3|cpm  (defaults to vq3)
     *        &page=1
     */
    public function records(Request $request)
    {
        $data = $request->validate([
            'physics' => 'nullable|in:vq3,cpm',
            'page' => 'nullable|integer|min:1|max:1000',
        ]);

        $physics = $data['physics'] ?? 'vq3';
        $page = $data['page'] ?? 1;
        $perPage = 100;

        $records = Record::query()
            ->where('physics', $physics)
            ->with(['user:id,name,plain_name,country,profile_photo_path'])
            ->orderBy('date_set', 'DESC')
            ->paginate($perPage, ['id', 'name', 'country', 'mdd_id', 'mapname', 'rank', 'time', 'date_set', 'physics', 'mode'], 'page', $page);

        return response()->json($records);
    }

    /**
     * Paginated map list for the launcher's Maps tab. Newest first,
     * optional name search. The launcher intentionally doesn't expose
     * the web's MapFilters surface - if the user wants to filter by
     * weapon / gametype / NSFW / etc. they click through to the
     * matching map page and get the web's full filter UI.
     *
     * Query: ?page=1  &search=optional-substring
     */
    public function maps(Request $request)
    {
        $data = $request->validate([
            'page' => 'nullable|integer|min:1|max:1000',
            'search' => 'nullable|string|max:64',
        ]);

        $page = $data['page'] ?? 1;
        $search = $data['search'] ?? null;
        $perPage = 50;

        $query = Map::query()
            ->select('id', 'name', 'author', 'thumbnail', 'physics', 'gametype', 'is_nsfw', 'date_added')
            ->orderBy('date_added', 'DESC')
            ->orderBy('id', 'DESC');

        if ($search !== null && $search !== '') {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        return response()->json(
            $query->paginate($perPage, ['*'], 'page', $page)
        );
    }

    /**
     * Request a YouTube render for a demo the launcher already
     * uploaded. Mirrors the web RenderRequestController flow, with two
     * key differences:
     *
     *  - lookup by file_hash OR demo_id (launcher has the hash locally
     *    from uploaded.json without needing the demo_id round-trip)
     *  - record_id is optional (launcher renders are demo-driven, not
     *    record-driven; demome's pipeline handles record_id=null)
     *
     * If the demo already has a non-failed RenderedVideo we short-
     * circuit and return its current status / youtube_url so the
     * launcher can show "already rendered" without a second queue
     * entry. Same 20-renders-per-day quota as the web button, shared
     * cache key so the user can't exceed it by bouncing between web
     * and launcher.
     *
     * Notification on completion comes for free - DemomeController's
     * markPublished() already fires a `render_completed` Notification
     * when the YouTube upload succeeds, regardless of source.
     */
    public function renderVideo(Request $request)
    {
        $data = $request->validate([
            'demo_id' => 'nullable|integer|exists:uploaded_demos,id',
            'file_hash' => 'nullable|string|size:32',
        ]);

        if (empty($data['demo_id']) && empty($data['file_hash'])) {
            return response()->json([
                'error' => 'Pass either demo_id or file_hash.',
            ], 422);
        }

        $user = $request->user();

        if (! $user->canUploadDemos()) {
            return response()->json([
                'error' => 'Your account is restricted from rendering.',
            ], 403);
        }

        $demo = isset($data['demo_id'])
            ? UploadedDemo::find($data['demo_id'])
            : UploadedDemo::where('file_hash', strtolower($data['file_hash']))->first();

        if (! $demo) {
            return response()->json([
                'error' => 'Demo not found. Upload it first via /upload-demo.',
                'needs_upload' => true,
            ], 404);
        }

        // Already in the pipeline (any non-failed state) - hand back
        // whatever we have so the launcher can show progress instead
        // of double-queueing.
        $existing = RenderedVideo::where('demo_id', $demo->id)
            ->whereIn('status', ['pending', 'rendering', 'uploading', 'completed'])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return response()->json([
                'already_queued' => true,
                'id' => $existing->id,
                'status' => $existing->status,
                'youtube_url' => $existing->youtube_url,
                'youtube_video_id' => $existing->youtube_video_id,
            ]);
        }

        // Same daily quota as the web request flow - shared cache key
        // so a user can't sidestep the cap by alternating between
        // /render-request on the web and /render-video here.
        $cacheKey = "render_requests_user_{$user->id}_" . now()->format('Y-m-d');
        $todayCount = Cache::get($cacheKey, 0);
        if ($todayCount >= 20) {
            return response()->json([
                'error' => 'Daily render limit reached (20/day).',
                'remaining' => 0,
            ], 429);
        }

        $demoUrl = config('app.url') . "/api/demome/download-demo/{$demo->id}";

        $video = RenderedVideo::create([
            'map_name' => $demo->map_name,
            'player_name' => $demo->player_name,
            'physics' => $demo->physics,
            'time_ms' => $demo->time_ms,
            'gametype' => $demo->gametype,
            'demo_id' => $demo->id,
            'record_id' => null,
            'user_id' => $user->id,
            'source' => 'launcher',
            'requested_by' => $user->name,
            'status' => 'pending',
            'priority' => 0,
            'demo_url' => $demoUrl,
            'demo_filename' => $demo->original_filename,
        ]);

        Cache::put($cacheKey, $todayCount + 1, now()->endOfDay());

        $queuePosition = RenderedVideo::where('status', 'pending')
            ->where('id', '<', $video->id)
            ->count() + 1;

        return response()->json([
            'success' => true,
            'id' => $video->id,
            'status' => 'pending',
            'queue_position' => $queuePosition,
            'remaining_today' => 20 - ($todayCount + 1),
        ]);
    }

    /**
     * Fast status check for a render the launcher previously queued.
     * Used by the Library view to refresh the YouTube link without
     * the user having to wait for the next notifications poll.
     * Cheaper than fetching all notifications.
     */
    public function renderStatus(Request $request)
    {
        $data = $request->validate([
            'demo_id' => 'nullable|integer|exists:uploaded_demos,id',
            'file_hash' => 'nullable|string|size:32',
        ]);

        if (empty($data['demo_id']) && empty($data['file_hash'])) {
            return response()->json([
                'error' => 'Pass either demo_id or file_hash.',
            ], 422);
        }

        $demo = isset($data['demo_id'])
            ? UploadedDemo::find($data['demo_id'])
            : UploadedDemo::where('file_hash', strtolower($data['file_hash']))->first();

        if (! $demo) {
            return response()->json([
                'has_render' => false,
                'reason' => 'demo_not_uploaded',
            ]);
        }

        $video = RenderedVideo::where('demo_id', $demo->id)
            ->orderByDesc('id')
            ->first();

        if (! $video) {
            return response()->json([
                'has_render' => false,
                'demo_id' => $demo->id,
            ]);
        }

        return response()->json([
            'has_render' => true,
            'demo_id' => $demo->id,
            'id' => $video->id,
            'status' => $video->status,
            'youtube_url' => $video->youtube_url,
            'youtube_video_id' => $video->youtube_video_id,
        ]);
    }
}
