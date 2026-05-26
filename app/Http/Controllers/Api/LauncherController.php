<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDemoJob;
use App\Models\Map;
use App\Models\Notification;
use App\Models\Record;
use App\Models\RecordNotification;
use App\Models\UploadedDemo;
use App\Services\ServerListService;
use Illuminate\Http\Request;
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
}
