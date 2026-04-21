<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\UploadedDemo;
use App\Models\RenderedVideo;
use App\Services\DemoProcessorService;
use App\Jobs\ProcessDemoJob;

class DemosController extends Controller
{
    protected $demoProcessor;

    public function __construct(DemoProcessorService $demoProcessor)
    {
        // Default: protect most actions with auth. Exempt index and download.
        // Also exempt the main upload action so the demos page can accept
        // anonymous uploads directly.
        $except = ['index', 'download', 'upload'];
        if (app()->environment('local')) {
            $except = array_merge($except, ['debugDetect', 'debugUpload']);
        }
        $this->middleware('auth')->except($except);
        $this->demoProcessor = $demoProcessor;
    }

    /**
     * Attempt to mkdir with retries; throw exception if it ultimately fails.
     */
    private function attemptMkDirWithRetries(string $fullPath)
    {
        if (is_dir($fullPath)) {
            return;
        }

        $created = false;
        for ($i = 0; $i < 8; $i++) {
            if (@mkdir($fullPath, 0775, true) || is_dir($fullPath)) {
                $created = true;
                break;
            }
            usleep(50000); // 50ms
        }

        if (!$created) {
            throw new \Exception("Unable to create a directory at {$fullPath}.");
        }
    }

    /**
     * Compute all demo count badges from a single GROUP BY query instead of 20+ individual COUNTs.
     */
    private function computeDemoCounts($baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->selectRaw('gametype, status, COUNT(*) as cnt')
            ->groupBy('gametype', 'status')
            ->get();

        $counts = [
            'all' => 0, 'online' => 0, 'offline' => 0,
            'uploaded' => 0, 'assigned' => 0, 'fallback_assigned' => 0,
            'processed' => 0, 'failed_validity' => 0, 'failed' => 0, 'unsupported_version' => 0,
            'online_assigned' => 0, 'online_fallback_assigned' => 0, 'online_processed' => 0,
            'online_failed_validity' => 0, 'online_failed' => 0,
            'offline_assigned' => 0, 'offline_fallback_assigned' => 0, 'offline_processed' => 0,
            'offline_failed_validity' => 0, 'offline_failed' => 0,
        ];

        $statusMap = [
            'assigned' => 'assigned',
            'fallback-assigned' => 'fallback_assigned',
            'processed' => 'processed',
            'failed-validity' => 'failed_validity',
            'failed' => 'failed',
            'unsupported-version' => 'unsupported_version',
            'uploaded' => 'uploaded',
            'pending' => 'uploaded',
            'processing' => 'uploaded',
        ];

        foreach ($rows as $row) {
            $cnt = (int) $row->cnt;
            $gt = $row->gametype;
            $isOnline = $gt !== null && str_starts_with($gt, 'm');
            $isOffline = $gt !== null && !str_starts_with($gt, 'm');

            $counts['all'] += $cnt;

            if ($isOnline) {
                $counts['online'] += $cnt;
            } elseif ($isOffline) {
                $counts['offline'] += $cnt;
            }

            $key = $statusMap[$row->status] ?? null;
            if ($key) {
                $counts[$key] = ($counts[$key] ?? 0) + $cnt;

                if ($isOnline) {
                    $onlineKey = "online_{$key}";
                    if (isset($counts[$onlineKey])) {
                        $counts[$onlineKey] += $cnt;
                    }
                } elseif ($isOffline) {
                    $offlineKey = "offline_{$key}";
                    if (isset($counts[$offlineKey])) {
                        $counts[$offlineKey] += $cnt;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * Display demo upload page
     */
    public function index(Request $request)
    {
        // Get current user
        $currentUser = Auth::user();
        $isAdmin = ($currentUser && ((isset($currentUser->is_admin) && $currentUser->is_admin) || (isset($currentUser->admin) && $currentUser->admin)));

        // Check which props Inertia is requesting (partial reload)
        $only = $request->header('X-Inertia-Partial-Data');
        $partialProps = $only ? explode(',', $only) : [];
        $isPartial = !empty($partialProps);

        $needs = function ($prop) use ($isPartial, $partialProps) {
            return !$isPartial || in_array($prop, $partialProps);
        };

        // Get sorting parameters
        $sortBy = $request->input('sort', 'created_at');
        $sortOrder = $request->input('order', 'desc');

        // Get filter parameters
        $filterTab = $request->input('tab', 'all');
        $filterStatus = $request->input('status', 'all');
        $searchQuery = $request->input('search', '');

        // Get advanced filter parameters (admin only)
        $confidenceFilter = $request->input('confidence');
        $showOtherUserMatches = $request->input('other_user_matches');
        $uploadedBy = $request->input('uploaded_by');

        // Validate sort column
        $allowedColumns = ['id', 'original_filename', 'processed_filename', 'map_name', 'time_ms', 'status', 'created_at', 'gametype', 'physics'];
        if (!in_array($sortBy, $allowedColumns)) {
            $sortBy = 'created_at';
        }

        // Validate sort order
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        // Browse parameters (needed for both full and partial loads)
        $browseTab = $request->input('browse_tab', 'all');
        $browseStatus = $request->input('browse_status', 'all');
        $browseSearch = $request->input('browse_search', '');
        $browseSortBy = $request->input('browse_sort', 'created_at');
        $browseSortOrder = $request->input('browse_order', 'desc');

        if (!in_array($browseSortBy, $allowedColumns)) {
            $browseSortBy = 'created_at';
        }
        if (!in_array($browseSortOrder, ['asc', 'desc'])) {
            $browseSortOrder = 'desc';
        }

        // On full page load, return immediately with null data - frontend will partial reload
        if (!$isPartial) {
            $downloadLimitInfo = null;
            $uploadLimitInfo = null;

            if ($currentUser) {
                $downloadLimitInfo = [
                    'used' => Cache::get("demo_download_user_{$currentUser->id}", 0),
                    'limit' => 50,
                    'remaining' => max(0, 50 - Cache::get("demo_download_user_{$currentUser->id}", 0)),
                    'isGuest' => false,
                ];
                $uploadLimitInfo = [
                    'used' => Cache::get("demo_upload_rate_limit_{$currentUser->id}", 0),
                    'limit' => 100000,
                    'remaining' => max(0, 100000 - Cache::get("demo_upload_rate_limit_{$currentUser->id}", 0)),
                    'isGuest' => false,
                ];
            } else {
                $ip = request()->ip();
                $downloadLimitInfo = [
                    'used' => Cache::get("demo_download_ip_{$ip}", 0),
                    'limit' => 1,
                    'remaining' => max(0, 1 - Cache::get("demo_download_ip_{$ip}", 0)),
                    'isGuest' => true,
                ];
                $uploadLimitInfo = [
                    'used' => Cache::get("demo_upload_rate_limit_guest_{$ip}", 0),
                    'limit' => 100,
                    'remaining' => max(0, 100 - Cache::get("demo_upload_rate_limit_guest_{$ip}", 0)),
                    'isGuest' => true,
                ];
            }

            return Inertia::render('Demos/Index', [
                'userDemos' => null,
                'publicDemos' => null,
                'demoCounts' => $isAdmin
                    ? Cache::get('demo_counts_admin')
                    : ($currentUser ? Cache::get("demo_counts_user_{$currentUser->id}") : null),
                'browseCounts' => Cache::get('demo_counts_browse'),
                'downloadLimitInfo' => $downloadLimitInfo,
                'uploadLimitInfo' => $uploadLimitInfo,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'browseTab' => $browseTab,
                'browseStatus' => $browseStatus,
                'browseSearch' => $browseSearch,
                'browseSortBy' => $browseSortBy,
                'browseSortOrder' => $browseSortOrder,
                'userSearch' => $searchQuery,
                'confidenceFilter' => $confidenceFilter,
                'showOtherUserMatches' => $showOtherUserMatches,
                'uploadedBy' => $uploadedBy,
            ]);
        }

        // --- Partial reload: fetch actual data ---

        // --- User's own uploads (only when needed) ---
        $userDemos = null;
        $demoCounts = null;
        if (Auth::check() && ($needs('userDemos') || $needs('demoCounts'))) {
            if ($isAdmin) {
                $query = UploadedDemo::with(['record.user', 'user', 'offlineRecord', 'suggestedUser']);

                if ($filterTab === 'online') {
                    $query->where('gametype', 'LIKE', 'm%');
                } elseif ($filterTab === 'offline') {
                    $query->where('gametype', 'NOT LIKE', 'm%')->whereNotNull('gametype');
                }

                if ($filterStatus === 'assigned') {
                    $query->whereIn('status', ['assigned', 'fallback-assigned']);
                } elseif ($filterStatus === 'fallback-assigned') {
                    $query->where('status', 'fallback-assigned');
                } elseif ($filterStatus === 'processed') {
                    $query->where('status', 'processed');
                } elseif ($filterStatus === 'failed-validity') {
                    $query->where('status', 'failed-validity');
                } elseif ($filterStatus === 'failed') {
                    $query->where('status', 'failed');
                } elseif ($filterStatus === 'unsupported-version') {
                    $query->where('status', 'unsupported-version');
                } elseif ($filterStatus === 'uploaded') {
                    $query->whereIn('status', ['uploaded', 'pending', 'processing']);
                }

                if (!empty($searchQuery)) {
                    $query->where(function($q) use ($searchQuery) {
                        $q->where('original_filename', 'LIKE', '%' . $searchQuery . '%')
                          ->orWhere('processed_filename', 'LIKE', '%' . $searchQuery . '%');
                    });
                }

                if ($confidenceFilter) {
                    switch ($confidenceFilter) {
                        case '90-99': $query->whereBetween('name_confidence', [90, 99]); break;
                        case '80-89': $query->whereBetween('name_confidence', [80, 89]); break;
                        case '70-79': $query->whereBetween('name_confidence', [70, 79]); break;
                        case '60-69': $query->whereBetween('name_confidence', [60, 69]); break;
                        case '50-59': $query->whereBetween('name_confidence', [50, 59]); break;
                        case 'below-50': $query->where('name_confidence', '<', 50); break;
                    }
                }

                if ($showOtherUserMatches) {
                    $query->where('name_confidence', 100)
                          ->whereNotNull('suggested_user_id')
                          ->where('suggested_user_id', '!=', Auth::id());
                }

                if ($uploadedBy) {
                    $uploaderUser = \App\Models\User::where('name', $uploadedBy)->first();
                    if ($uploaderUser) {
                        $query->where('user_id', $uploaderUser->id);
                    }
                }

                if ($sortBy === 'status') {
                    $query->orderByRaw("FIELD(status, 'assigned', 'fallback-assigned', 'processed', 'processing', 'pending', 'uploaded', 'failed-validity', 'failed')");
                } else {
                    $query->orderBy($sortBy, $sortOrder);
                }

                if ($needs('userDemos')) $userDemos = $query->paginate(20, ['*'], 'userPage');
                if ($needs('demoCounts')) $demoCounts = Cache::remember('demo_counts_admin', 60, fn () => $this->computeDemoCounts(UploadedDemo::query()));
            } else {
                $query = UploadedDemo::where('user_id', $currentUser->id)
                    ->with(['record.user', 'offlineRecord']);

                if ($filterTab === 'online') {
                    $query->where('gametype', 'LIKE', 'm%');
                } elseif ($filterTab === 'offline') {
                    $query->where('gametype', 'NOT LIKE', 'm%')->whereNotNull('gametype');
                }

                if ($filterStatus === 'assigned') {
                    $query->whereIn('status', ['assigned', 'fallback-assigned']);
                } elseif ($filterStatus === 'fallback-assigned') {
                    $query->where('status', 'fallback-assigned');
                } elseif ($filterStatus === 'processed') {
                    $query->where('status', 'processed');
                } elseif ($filterStatus === 'failed-validity') {
                    $query->where('status', 'failed-validity');
                } elseif ($filterStatus === 'failed') {
                    $query->where('status', 'failed');
                } elseif ($filterStatus === 'unsupported-version') {
                    $query->where('status', 'unsupported-version');
                } elseif ($filterStatus === 'uploaded') {
                    $query->whereIn('status', ['uploaded', 'pending', 'processing']);
                }

                if (!empty($searchQuery)) {
                    $query->where(function($q) use ($searchQuery) {
                        $q->where('original_filename', 'LIKE', '%' . $searchQuery . '%')
                          ->orWhere('processed_filename', 'LIKE', '%' . $searchQuery . '%');
                    });
                }

                if ($sortBy === 'status') {
                    $query->orderByRaw("FIELD(status, 'assigned', 'fallback-assigned', 'processed', 'processing', 'pending', 'uploaded', 'failed-validity', 'failed')");
                } else {
                    $query->orderBy($sortBy, $sortOrder);
                }

                if ($needs('userDemos')) $userDemos = $query->paginate(20, ['*'], 'userPage');
                if ($needs('demoCounts')) $demoCounts = Cache::remember("demo_counts_user_{$currentUser->id}", 3600, fn () => $this->computeDemoCounts(UploadedDemo::where('user_id', $currentUser->id)));
            }
        }

        // --- Browse section (only when needed) ---
        $publicDemos = null;
        $browseCounts = null;

        if ($needs('publicDemos') || $needs('browseCounts')) {
            if ($needs('publicDemos')) {
                $query = UploadedDemo::with(['record.user', 'user', 'offlineRecord']);

                if ($browseStatus === 'assigned') {
                    $query->whereIn('status', ['assigned', 'fallback-assigned']);
                } elseif ($browseStatus === 'fallback-assigned') {
                    $query->where('status', 'fallback-assigned');
                } elseif ($browseStatus === 'processed') {
                    $query->where('status', 'processed');
                } elseif ($browseStatus === 'failed-validity') {
                    $query->where('status', 'failed-validity');
                } elseif ($browseStatus === 'failed') {
                    $query->where('status', 'failed');
                } else {
                    $query->whereIn('status', ['assigned', 'fallback-assigned', 'processed', 'failed-validity', 'failed']);
                }

                if ($browseTab === 'online') {
                    $query->where('gametype', 'LIKE', 'm%');
                } elseif ($browseTab === 'offline') {
                    $query->where('gametype', 'NOT LIKE', 'm%')->whereNotNull('gametype');
                }

                if (!empty($browseSearch)) {
                    $query->where(function($q) use ($browseSearch) {
                        $q->where('original_filename', 'LIKE', "%{$browseSearch}%")
                          ->orWhere('processed_filename', 'LIKE', "%{$browseSearch}%");
                    });
                }

                $browseUploadedBy = $request->input('browse_uploaded_by');
                if ($browseUploadedBy) {
                    $uploaderUser = \App\Models\User::where('plain_name', $browseUploadedBy)->first();
                    if ($uploaderUser) {
                        $query->where('user_id', $uploaderUser->id);
                    } else {
                        $query->whereRaw('1 = 0'); // no match
                    }
                }

                if ($browseSortBy === 'status') {
                    $query->orderByRaw("FIELD(status, 'assigned', 'fallback-assigned', 'processed', 'failed-validity', 'failed')");
                } else {
                    $query->orderBy($browseSortBy, $browseSortOrder);
                }

                $publicDemos = $query->paginate(20, ['*'], 'browsePage');
            }

            if ($needs('browseCounts')) {
                $browseCounts = Cache::remember('demo_counts_browse', 3600, fn () => $this->computeDemoCounts(
                    UploadedDemo::whereIn('status', ['assigned', 'fallback-assigned', 'processed', 'failed-validity', 'failed'])
                ));
            }
        }

        $data = [
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'browseTab' => $browseTab,
            'browseStatus' => $browseStatus,
            'browseSearch' => $browseSearch,
            'browseSortBy' => $browseSortBy,
            'browseSortOrder' => $browseSortOrder,
            'userSearch' => $searchQuery,
            'confidenceFilter' => $confidenceFilter,
            'showOtherUserMatches' => $showOtherUserMatches,
            'uploadedBy' => $uploadedBy,
            'browseUploadedBy' => $request->input('browse_uploaded_by', ''),
        ];

        if ($needs('userDemos')) $data['userDemos'] = $userDemos;
        if ($needs('demoCounts')) $data['demoCounts'] = $demoCounts;
        if ($needs('publicDemos')) $data['publicDemos'] = $publicDemos;
        if ($needs('browseCounts')) $data['browseCounts'] = $browseCounts;

        // Download & Upload limits (only on full load)
        if (!$isPartial) {
            $rateLimitKey = $currentUser
                ? "demo_download_user_{$currentUser->id}"
                : "demo_download_ip_" . request()->ip();

            $downloadsToday = Cache::get($rateLimitKey, 0);
            $maxDownloads = $currentUser ? 50 : 1;

            $data['downloadLimitInfo'] = [
                'used' => $downloadsToday,
                'limit' => $maxDownloads,
                'remaining' => max(0, $maxDownloads - $downloadsToday),
                'isGuest' => !$currentUser,
            ];

            $uploadRateLimitKey = $currentUser
                ? "demo_upload_rate_limit_{$currentUser->id}"
                : "demo_upload_rate_limit_guest_" . request()->ip();

            $uploadsUsed = Cache::get($uploadRateLimitKey, 0);
            $maxUploads = $currentUser ? 100000 : 100;

            $data['uploadLimitInfo'] = [
                'used' => $uploadsUsed,
                'limit' => $maxUploads,
                'remaining' => max(0, $maxUploads - $uploadsUsed),
                'isGuest' => !$currentUser,
            ];
        }

        return Inertia::render('Demos/Index', $data);
    }

    /**
     * Autocomplete search for demo uploaders
     */
    public function searchUploaders(Request $request)
    {
        $q = $request->input('q', '');
        if (strlen($q) < 2) return response()->json([]);

        $users = \App\Models\User::where('plain_name', 'LIKE', "%{$q}%")
            ->whereHas('uploadedDemos')
            ->select('plain_name')
            ->limit(10)
            ->pluck('plain_name');

        return response()->json($users);
    }

    /**
     * Upload demos with rate limiting and queue processing
     */
    public function upload(Request $request)
    {
        $currentUser = Auth::user();

        // Check upload restrictions for logged-in users
        if ($currentUser && !$currentUser->canUploadDemos()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been restricted from uploading demos. Please contact an administrator.',
            ], 403);
        }

        // Rate limiting
        if ($currentUser) {
            // Logged-in users: generous limit
            $RATE_LIMIT_MAX = 100000;
            $rateLimitKey = "demo_upload_rate_limit_{$currentUser->id}";
            $rateLimitTtl = 300; // 5 minutes
        } else {
            // Guests: 100 per day per IP
            $RATE_LIMIT_MAX = 100;
            $rateLimitKey = "demo_upload_rate_limit_guest_" . $request->ip();
            $rateLimitTtl = 86400; // 24 hours
        }

        $currentUploads = Cache::get($rateLimitKey, 0);

        if ($currentUploads >= $RATE_LIMIT_MAX) {
            return response()->json([
                'success' => false,
                'message' => $currentUser
                    ? "Rate limit exceeded. You can upload maximum {$RATE_LIMIT_MAX} demos per 5 minutes."
                    : "Guest upload limit reached (100/day). Log in for unlimited uploads.",
            ], 429);
        }

        $userId = $currentUser?->id;

        $request->validate([
            'demos' => 'required|array|min:1|max:100000', // Max 100,000 uploads per request (files or archives)
            // Allow larger files for archives; per-file max 512MB (512000 KB)
            'demos.*' => 'required|file|max:512000',
        ]);

        $uploadedDemos = [];
        $queuedDemos = [];
        $errors = [];
        $filesProcessed = 0;

        // Check total size limit (10GB total)
        $totalSize = 0;
        foreach ($request->file('demos') as $demoFile) {
            $totalSize += $demoFile->getSize();
        }

        if ($totalSize > 10737418240) { // 10GB
            return response()->json([
                'success' => false,
                'message' => 'Total upload size exceeds 10GB limit.',
            ], 413);
        }

        // Phase 1: Separate archives from demo files, compute hashes
        $demoFiles = $request->file('demos');
        $demoCandidate = []; // ['file' => UploadedFile, 'hash' => string, 'name' => string]
        $archiveTempDir = storage_path('app/temp_archives');

        foreach ($demoFiles as $demoFile) {
            try {
                $extension = strtolower($demoFile->getClientOriginalExtension());
                $originalName = $demoFile->getClientOriginalName();

                // Quick archive detection: check extension and filename first (skip content check for .dm_* files)
                $isArchiveExt = $this->isArchiveExtension($extension);
                $isArchiveName = (bool) preg_match('/\.(zip|rar|7z)$/i', $originalName);
                $isArchive = $isArchiveExt || $isArchiveName;

                // Only do expensive content-based check if extension is ambiguous
                if (!$isArchive && !preg_match('/^dm_\d+$/', $extension)) {
                    try {
                        $isArchive = $this->isArchiveByContent($demoFile->getPathname());
                    } catch (\Throwable $t) {
                        // ignore
                    }
                }

                if ($isArchive) {
                    try {
                        $archiveFilename = 'archive_' . uniqid() . '_' . $originalName;
                        $archivePath = $archiveTempDir . '/' . $archiveFilename;
                        if (!is_dir($archiveTempDir)) {
                            mkdir($archiveTempDir, 0755, true);
                        }
                        $demoFile->move($archiveTempDir, $archiveFilename);
                        \App\Jobs\ExtractAndQueueArchiveJob::dispatch($archivePath, $userId, $originalName);
                        $filesProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = $originalName . ': Failed to queue archive - ' . $e->getMessage();
                    }
                    continue;
                }

                if (!preg_match('/^dm_\d+$/', $extension)) {
                    $errors[] = $originalName . ': Invalid demo file format';
                    continue;
                }

                // Compute hash
                $fileHash = md5_file($demoFile->getPathname());
                $demoCandidate[] = [
                    'file' => $demoFile,
                    'hash' => $fileHash,
                    'name' => $originalName,
                ];
            } catch (\Exception $e) {
                $errors[] = $demoFile->getClientOriginalName() . ': Upload failed - ' . $e->getMessage();
            }
        }

        // Phase 2: Bulk duplicate detection (2 queries instead of 2×N)
        if (!empty($demoCandidate)) {
            $allHashes = array_column($demoCandidate, 'hash');
            $allNames = array_column($demoCandidate, 'name');

            // Batch query: existing demos by hash
            $existingByHash = UploadedDemo::whereIn('file_hash', $allHashes)
                ->get()
                ->keyBy('file_hash');

            // Batch query: existing demos by filename for this user (skip for guests)
            $existingByName = $userId
                ? UploadedDemo::where('user_id', $userId)
                    ->whereIn('original_filename', $allNames)
                    ->get()
                    ->keyBy('original_filename')
                : collect();

            // Phase 3: Filter, create records, store files, dispatch
            $reuploadableStatuses = ['failed', 'failed-validity', 'unsupported-version'];
            $replacedCount = 0;
            foreach ($demoCandidate as $candidate) {
                try {
                    $demoFile = $candidate['file'];
                    $fileHash = $candidate['hash'];
                    $originalName = $candidate['name'];

                    // Check hash duplicate
                    $replacedThisDemo = false;
                    if ($existingByHash->has($fileHash)) {
                        $existing = $existingByHash->get($fileHash);
                        if (in_array($existing->status, $reuploadableStatuses)) {
                            $this->cleanupFailedDemo($existing);
                            $existingByHash->forget($fileHash);
                            $replacedThisDemo = true;
                        } else {
                            $demoName = $existing->processed_filename ?: $existing->original_filename;
                            $errors[] = $originalName . ': Duplicate file content (already uploaded as: ' . $demoName . ')';
                            continue;
                        }
                    }

                    // Check filename duplicate
                    if ($existingByName->has($originalName)) {
                        $existing = $existingByName->get($originalName);
                        if (in_array($existing->status, $reuploadableStatuses)) {
                            $this->cleanupFailedDemo($existing);
                            $existingByName->forget($originalName);
                            $replacedThisDemo = true;
                        } else {
                            $errors[] = $originalName . ': Filename already uploaded by you';
                            continue;
                        }
                    }

                    if ($replacedThisDemo) $replacedCount++;

                    // Create DB record (catch unique constraint for same-hash files within one batch)
                    try {
                        $demo = UploadedDemo::create([
                            'original_filename' => $originalName,
                            'file_path' => '',
                            'file_size' => $demoFile->getSize(),
                            'file_hash' => $fileHash,
                            'user_id' => $userId,
                            'status' => 'uploaded',
                        ]);
                    } catch (\Illuminate\Database\QueryException $qe) {
                        if (str_contains($qe->getMessage(), 'Duplicate entry')) {
                            $errors[] = $originalName . ': Duplicate file content (same hash in this batch)';
                            continue;
                        }
                        throw $qe;
                    }

                    // Store file and update path
                    $path = $this->storeUploadedDemoLocally($demoFile, $demo->id);
                    $demo->update(['file_path' => $path]);

                    // Dispatch for processing
                    ProcessDemoJob::dispatch($demo);

                    $queuedDemos[] = $demo;
                    $filesProcessed++;
                } catch (\Exception $e) {
                    $errors[] = $candidate['name'] . ': Upload failed - ' . $e->getMessage();
                }
            }
        }

        // Memory cleanup
        gc_collect_cycles();

        // Update rate limit counter
        Cache::put($rateLimitKey, $currentUploads + $filesProcessed, now()->addSeconds($rateLimitTtl));

        // Count error types for summary
        $duplicateCount = count(array_filter($errors, fn($e) => str_contains($e, 'Duplicate') || str_contains($e, 'already uploaded')));
        $otherErrorCount = count($errors) - $duplicateCount;

        Log::info("Demo upload batch completed", [
            'user_id' => $userId,
            'total_files' => count($demoFiles),
            'queued' => count($queuedDemos),
            'duplicates' => $duplicateCount,
            'errors' => $otherErrorCount,
        ]);

        return response()->json([
            'success' => true,
            'uploaded' => $queuedDemos,
            'errors' => $errors,
            'message' => count($queuedDemos) . ' demo(s) queued for processing' .
                        (count($errors) > 0 ? ', ' . count($errors) . ' failed' : ''),
            'summary' => [
                'total_received' => count($demoFiles),
                'queued' => count($queuedDemos),
                'duplicates' => $duplicateCount,
                'errors' => $otherErrorCount,
                'replaced' => $replacedCount ?? 0,
            ],
            'queue_info' => [
                'total_queued' => count($queuedDemos),
                'estimated_completion' => now()->addSeconds(count($queuedDemos) * 30)->format('H:i:s'),
            ]
        ]);
    }

    /**
     * Download a demo
     */
    public function download(UploadedDemo $demo)
    {
        // Allow download for owner, admins, or if the demo is assigned to a public record (online or offline)
        $currentUser = Auth::user();
        $isAdmin = ($currentUser && ((isset($currentUser->is_admin) && $currentUser->is_admin) || (isset($currentUser->admin) && $currentUser->admin)));

        // Allow download if: user is owner, user is admin, demo has online record, or demo has offline record
        $hasPublicRecord = $demo->record || $demo->offlineRecord;

        if ($demo->user_id !== optional($currentUser)->id && !$hasPublicRecord && !$isAdmin) {
            abort(403, 'Unauthorized');
        }

        // Rate limiting for downloads (skip for admins and demo owners)
        if (!$isAdmin && $demo->user_id !== optional($currentUser)->id) {
            // Use both user ID (if logged in) and IP address for rate limiting
            $rateLimitKey = $currentUser
                ? "demo_download_user_{$currentUser->id}"
                : "demo_download_ip_" . request()->ip();

            $downloadsToday = Cache::get($rateLimitKey, 0);
            $maxDownloads = $currentUser ? 50 : 1; // Authenticated: 50/day, Guest: 1/day

            if ($downloadsToday >= $maxDownloads) {
                // Return a JSON response for AJAX requests, or redirect with error for direct links
                if (request()->wantsJson() || request()->expectsJson()) {
                    return response()->json([
                        'error' => 'Download limit reached',
                        'message' => $currentUser
                            ? "You've reached your download limit of {$maxDownloads} demos per day. Limit resets at midnight."
                            : "You've reached the guest download limit (1 demo per day). Please create an account to download up to 50 demos per day!",
                        'isGuest' => !$currentUser,
                        'limit' => $maxDownloads,
                    ], 429);
                } else {
                    // For direct download links, redirect back with error message
                    return back()->with('danger', $currentUser
                        ? "Download limit reached. You can download maximum {$maxDownloads} demo" . ($maxDownloads > 1 ? 's' : '') . " per day."
                        : "You've reached the guest download limit (1 demo per day). Please create an account to download up to 50 demos per day!");
                }
            }

            // Increment counter (expires at end of day)
            $expiresAt = now()->endOfDay();
            Cache::put($rateLimitKey, $downloadsToday + 1, $expiresAt);

            Log::info('Demo download rate limit check', [
                'demo_id' => $demo->id,
                'user_id' => optional($currentUser)->id,
                'ip' => request()->ip(),
                'downloads_today' => $downloadsToday + 1,
                'limit' => $maxDownloads,
            ]);
        }

        // Increment download counter (skip for demo owner and admins to avoid inflating count)
        if (!$isAdmin && $demo->user_id !== optional($currentUser)->id) {
            $demo->incrementDownloads();
        }

        $filename = $demo->processed_filename ?: $demo->original_filename;

        // If file_path is empty/null, the file was lost (processed before Backblaze upload fix)
        if (empty($demo->file_path)) {
            abort(404, 'Demo file is no longer available - it was processed before cloud storage was enabled.');
        }

        // Check if demo is stored locally (failed or temp demos) or in Backblaze (processed demos)
        $isLocal = str_starts_with($demo->file_path, 'demos/temp/') ||
                   str_starts_with($demo->file_path, 'demos/failed/');

        if ($isLocal) {
            $fullPath = storage_path("app/{$demo->file_path}");
            if (file_exists($fullPath)) {
                // Try to extract from archive
                $contents = file_get_contents($fullPath);
                $extracted = $this->extractFromArchive($contents, $filename);
                if ($extracted) {
                    return response()->streamDownload(function() use ($extracted) {
                        echo $extracted['contents'];
                    }, $extracted['filename'], [
                        'Content-Type' => 'application/octet-stream',
                    ]);
                }
                return response()->download($fullPath, $filename);
            } else {
                abort(404, 'Demo file not found');
            }
        }

        try {
            // Download from Backblaze (processed demos)
            $fileContents = Storage::get($demo->file_path);

            // Try to extract the demo from 7z archive and serve the raw .dm_68 file
            $extracted = $this->extractFromArchive($fileContents, $filename);
            if ($extracted) {
                return response()->streamDownload(function() use ($extracted) {
                    echo $extracted['contents'];
                }, $extracted['filename'], [
                    'Content-Type' => 'application/octet-stream',
                ]);
            }

            // Fallback: serve the archive as-is
            return response()->streamDownload(function() use ($fileContents) {
                echo $fileContents;
            }, $filename, [
                'Content-Type' => 'application/octet-stream',
            ]);
        } catch (\Exception $e) {
            // Fallback: try local storage
            Log::warning('Storage retrieval failed, trying local storage', [
                'demo_id' => $demo->id,
                'file_path' => $demo->file_path,
                'error' => $e->getMessage(),
            ]);

            $fullPath = storage_path("app/{$demo->file_path}");
            if (file_exists($fullPath)) {
                return response()->download($fullPath, $filename);
            }

            Log::error('Demo file not found in Backblaze or local storage', [
                'demo_id' => $demo->id,
                'file_path' => $demo->file_path,
            ]);
            abort(404, 'Demo file not found');
        }
    }

    /**
     * Extract a file from a 7z archive in memory.
     * Returns ['filename' => ..., 'contents' => ...] or null on failure.
     */
    private function extractFromArchive(string $archiveContents, string $archiveFilename): ?array
    {
        // Only process 7z files
        if (!str_ends_with(strtolower($archiveFilename), '.7z')) {
            return null;
        }

        $tempDir = sys_get_temp_dir() . '/demo_extract_' . uniqid();
        $tempArchive = $tempDir . '/' . $archiveFilename;

        try {
            mkdir($tempDir, 0755, true);
            file_put_contents($tempArchive, $archiveContents);

            // Extract with 7z
            $cmd = sprintf('7z x %s -o%s -y 2>&1', escapeshellarg($tempArchive), escapeshellarg($tempDir));
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                return null;
            }

            // Find the extracted demo file (should be exactly one .dm_68 file)
            $files = glob($tempDir . '/*.dm_68') ?: glob($tempDir . '/*.dm_*');
            if (empty($files)) {
                // Try any file that's not the archive itself
                $allFiles = array_diff(scandir($tempDir), ['.', '..', basename($archiveFilename)]);
                $files = array_map(fn($f) => $tempDir . '/' . $f, $allFiles);
                $files = array_filter($files, 'is_file');
            }

            if (empty($files)) {
                return null;
            }

            $extractedFile = reset($files);
            $contents = file_get_contents($extractedFile);
            $extractedFilename = basename($extractedFile);

            return [
                'filename' => $extractedFilename,
                'contents' => $contents,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to extract demo archive', [
                'filename' => $archiveFilename,
                'error' => $e->getMessage(),
            ]);
            return null;
        } finally {
            // Clean up temp files
            if (is_dir($tempDir)) {
                array_map('unlink', glob($tempDir . '/*'));
                @rmdir($tempDir);
            }
        }
    }

    /**
     * Reprocess a demo
     */
    public function reprocess(UploadedDemo $demo)
    {
        $currentUser = Auth::user();
        $isAdmin = ($currentUser && ((isset($currentUser->is_admin) && $currentUser->is_admin) || (isset($currentUser->admin) && $currentUser->admin)));
        if ($demo->user_id !== optional($currentUser)->id && !$isAdmin) {
            abort(403, 'Unauthorized');
        }

        try {
            // Prepare demo for reprocessing
            // We need to ensure the original demo file exists in temp directory

            // First, check if we have the original file in failed directory
            $failedPath = storage_path("app/demos/failed/{$demo->id}/{$demo->original_filename}");
            $tempDir = storage_path("app/demos/temp/{$demo->id}");
            $tempPath = "{$tempDir}/{$demo->original_filename}";

            // Create temp directory
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            if (file_exists($failedPath)) {
                // Copy from failed directory
                copy($failedPath, $tempPath);
            } else {
                // Check if file exists in local storage
                $currentPath = storage_path("app/{$demo->file_path}");

                if (file_exists($currentPath)) {
                    // If it's a .7z file, we need to extract it first
                    if (str_ends_with($currentPath, '.7z')) {
                        // Extract the .7z file
                        $extractCmd = "7z x " . escapeshellarg($currentPath) . " -o" . escapeshellarg($tempDir) . " -y";
                        exec($extractCmd, $extractOutput, $extractReturnVar);

                        if ($extractReturnVar !== 0) {
                            throw new \Exception('Failed to extract compressed demo file for reprocessing');
                        }

                        // Find the extracted .dm_* file
                        $extractedFiles = glob($tempDir . '/*.dm_*');
                        if (empty($extractedFiles)) {
                            throw new \Exception('No demo file found after extraction');
                        }

                        // Rename to original filename if different
                        if (basename($extractedFiles[0]) !== $demo->original_filename) {
                            rename($extractedFiles[0], $tempPath);
                        }
                    } else {
                        // Copy the file
                        copy($currentPath, $tempPath);
                    }
                } else {
                    // File not found locally, try to download from Backblaze
                    try {
                        $compressedPath = "{$tempDir}/" . basename($demo->file_path);

                        // Download from Backblaze B2
                        $fileContents = Storage::get($demo->file_path);
                        file_put_contents($compressedPath, $fileContents);

                        // Extract the downloaded .7z file
                        if (str_ends_with($compressedPath, '.7z')) {
                            $extractCmd = "7z x " . escapeshellarg($compressedPath) . " -o" . escapeshellarg($tempDir) . " -y";
                            exec($extractCmd, $extractOutput, $extractReturnVar);

                            if ($extractReturnVar !== 0) {
                                throw new \Exception('Failed to extract compressed demo file from Backblaze');
                            }

                            // Find the extracted .dm_* file
                            $extractedFiles = glob($tempDir . '/*.dm_*');
                            if (empty($extractedFiles)) {
                                throw new \Exception('No demo file found after extraction from Backblaze');
                            }

                            // Rename to original filename if different
                            if (basename($extractedFiles[0]) !== $demo->original_filename) {
                                rename($extractedFiles[0], $tempPath);
                            }

                            // Remove the compressed file
                            unlink($compressedPath);
                        }
                    } catch (\Exception $e) {
                        throw new \Exception('Failed to download demo from Backblaze for reprocessing: ' . $e->getMessage());
                    }
                }
            }

            // Delete any existing offline record (will be recreated during reprocessing)
            if ($demo->offlineRecord) {
                $demo->offlineRecord->delete();
            }

            // Reset demo status and metadata, update file_path to temp location
            $demo->update([
                'status' => 'uploaded',
                'file_path' => "demos/temp/{$demo->id}/{$demo->original_filename}",
                'processed_filename' => null,
                'map_name' => null,
                'physics' => null,
                'gametype' => null,
                'player_name' => null,
                'q3df_login_name' => null,
                'q3df_login_name_colored' => null,
                'time_ms' => null,
                'processing_output' => null,
                'record_id' => null,
                'match_method' => null,
            ]);

            // Queue the demo for reprocessing (don't process synchronously)
            \App\Jobs\ProcessDemoJob::dispatch($demo);

            return response()->json([
                'success' => true,
                'message' => 'Demo queued for reprocessing',
                'demo' => $demo->fresh()->load('record.user'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reprocessing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get processing status for user's demos (polling endpoint)
     */
    public function status(Request $request)
    {
        $demoIds = $request->get('demo_ids');

        // If demo_ids provided and user is NOT logged in, allow public polling for those specific demos (guest uploads)
        if ($demoIds && is_array($demoIds) && !Auth::check()) {
            $demos = UploadedDemo::whereIn('id', $demoIds)->with(['record.user'])->get();
            return response()->json([
                'processing_demos' => $demos->filter(function ($d) { return in_array($d->status, ['uploaded', 'pending', 'processing']); })->values(),
                'completed_demos' => $demos->filter(function ($d) { return !in_array($d->status, ['uploaded', 'pending', 'processing']); })->values(),
                'queue_stats' => [
                    'total_queued' => UploadedDemo::whereIn('status', ['uploaded', 'pending'])->count(),
                    'total_processing' => UploadedDemo::where('status', 'processing')->count(),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        }

        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $currentUser = Auth::user();
        $isAdmin = ($currentUser && ((isset($currentUser->is_admin) && $currentUser->is_admin) || (isset($currentUser->admin) && $currentUser->admin)));

        $userId = $currentUser->id;

        // Get processing/queued demos for real-time updates. Admins see all queued demos.
        $processingQuery = UploadedDemo::whereIn('status', ['uploaded', 'pending', 'processing'])->with(['record.user']);
        if (!$isAdmin) {
            $processingQuery->where('user_id', $userId);
        }
        $processingDemos = $processingQuery->get();

        // Get queue statistics
        $queueStats = [
            'total_queued' => UploadedDemo::whereIn('status', ['uploaded', 'pending'])->count(),
            'total_processing' => UploadedDemo::where('status', 'processing')->count(),
            'user_queued' => $isAdmin ? UploadedDemo::whereIn('status', ['uploaded', 'pending'])->count() : UploadedDemo::where('user_id', $userId)->whereIn('status', ['uploaded', 'pending'])->count(),
            'user_processing' => $isAdmin ? UploadedDemo::where('status', 'processing')->count() : UploadedDemo::where('user_id', $userId)->where('status', 'processing')->count(),
        ];

        // Return recently completed demos (last 5 min) — no frontend tracking needed
        $recentCutoff = now()->subMinutes(5);
        $completedQuery = UploadedDemo::whereNotIn('status', ['uploaded', 'pending', 'processing'])
            ->where('updated_at', '>=', $recentCutoff)
            ->select(['id', 'original_filename', 'processed_filename', 'status', 'processing_output', 'map_name', 'time_ms', 'player_name', 'updated_at']);
        if (!$isAdmin) {
            $completedQuery->where('user_id', $userId);
        }
        $completedDemos = $completedQuery->orderBy('updated_at', 'desc')->limit(100)->get();

        // Also include tracked IDs if provided (for specific batch tracking)
        $trackingIds = $request->input('tracking_ids');
        if ($trackingIds && is_array($trackingIds)) {
            $trackedCompleted = UploadedDemo::whereIn('id', $trackingIds)
                ->whereNotIn('status', ['uploaded', 'pending', 'processing'])
                ->select(['id', 'original_filename', 'processed_filename', 'status', 'processing_output', 'map_name', 'time_ms', 'player_name', 'updated_at'])
                ->get();
            // Merge without duplicates
            $existingIds = $completedDemos->pluck('id')->toArray();
            foreach ($trackedCompleted as $demo) {
                if (!in_array($demo->id, $existingIds)) {
                    $completedDemos->push($demo);
                }
            }
        }

        return response()->json([
            'processing_demos' => $processingDemos,
            'completed_demos' => $completedDemos,
            'queue_stats' => $queueStats,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Local-only debug endpoint: return archive detection results for a single uploaded file.
     * This helps diagnose why .zip/.7z uploads are being rejected without needing to tail logs.
     */
    public function debugDetect(Request $request)
    {
        if (!app()->environment('local')) {
            abort(404);
        }

        $request->validate([
            'file' => 'required|file',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();

        $isArchiveExt = $this->isArchiveExtension($extension);
        $isArchiveName = (bool) preg_match('/\.(zip|rar|7z)$/i', $originalName);
        $isArchiveContent = false;
        try {
            $isArchiveContent = $this->isArchiveByContent($file->getPathname());
        } catch (\Throwable $t) {
            $error = $t->getMessage();
        }

        return response()->json([
            'original_name' => $originalName,
            'reported_extension' => $extension,
            'isArchiveExt' => $isArchiveExt,
            'isArchiveName' => $isArchiveName,
            'isArchiveContent' => $isArchiveContent,
            'client_path' => $file->getPathname(),
            'error' => $error ?? null,
        ]);
    }

    /**
     * Local-only debug upload endpoint to exercise archive extraction/upload logic without authentication.
     * Wraps a single 'file' input into 'demos' array and calls the normal upload flow.
     */
    public function debugUpload(Request $request)
    {
        if (!app()->environment('local')) {
            abort(404);
        }

        $request->validate([
            'file' => 'required|file',
        ]);

        // Create a new request instance that mimics the authenticated upload route
        $files = [$request->file('file')];

        // Use a new Request instance so we can set 'demos' as expected by upload()
        $newRequest = new Request();
        $newRequest->files->set('demos', $files);
        // Copy some headers that may be used by validation
        $newRequest->headers->add($request->headers->all());

        // If no user is authenticated, temporarily impersonate the first user for testing
        if (!Auth::check()) {
            $firstUser = \App\Models\User::first();
            if ($firstUser) {
                Auth::login($firstUser);
            }
        }

        // Call the existing upload flow
        return $this->upload($newRequest);
    }

    /**
     * Public upload endpoint for anonymous users.
     * Stores demos with user_id = null. Rate-limited by IP to avoid abuse.
     */
    public function uploadPublic(Request $request)
    {
        abort(404);
    }

    /**
     * Start processing all uploaded (unprocessed) demos for the current user
     */
    public function startProcessing()
    {
        $userId = Auth::id();
        if (!$userId) {
            abort(403, 'Unauthorized');
        }

        $demos = UploadedDemo::where('user_id', $userId)
            ->where('status', 'uploaded')
            ->get();

        $dispatched = 0;
        foreach ($demos as $demo) {
            ProcessDemoJob::dispatch($demo);
            $dispatched++;
        }

        Log::info("Started processing for user", [
            'user_id' => $userId,
            'dispatched' => $dispatched,
        ]);

        return response()->json([
            'success' => true,
            'dispatched' => $dispatched,
            'message' => "{$dispatched} demo(s) queued for processing",
        ]);
    }

    /**
     * Reprocess all failed demos (admin only)
     */
    public function reprocessAllFailed()
    {
        $currentUser = Auth::user();
        if (!$currentUser || !isset($currentUser->admin) || !$currentUser->admin) {
            abort(403, 'Unauthorized');
        }

        $failed = UploadedDemo::where('status', 'failed')->get();
        $dispatched = 0;
        $missing = 0;
        $demoIds = [];

        // Clear failed jobs from the failed_jobs table so retry counters reset
        \Illuminate\Support\Facades\DB::table('failed_jobs')
            ->where('payload', 'like', '%ProcessDemoJob%')
            ->delete();

        foreach ($failed as $demo) {
            if (!file_exists($demo->full_path)) {
                $missing++;
                continue;
            }

            // Clear the unique lock for this demo so ShouldBeUnique doesn't block
            $uniqueKey = 'laravel_unique_job:' . \App\Jobs\ProcessDemoJob::class . $demo->id;
            \Illuminate\Support\Facades\Cache::forget($uniqueKey);

            $demo->update([
                'status' => 'uploaded',
                'processing_output' => null,
            ]);

            \App\Jobs\ProcessDemoJob::dispatch($demo);
            $demoIds[] = $demo->id;
            $dispatched++;
        }

        return response()->json([
            'success' => true,
            'dispatched' => $dispatched,
            'missing' => $missing,
            'demo_ids' => $demoIds,
            'message' => "{$dispatched} failed demo(s) queued for reprocessing" . ($missing > 0 ? ", {$missing} skipped (missing files)" : ''),
        ]);
    }

    /**
     * Delete a demo (only if user owns it and it's not assigned)
     */
    public function destroy(UploadedDemo $demo)
    {
        // Allow deletion by owner or admins
        $currentUser = Auth::user();
        $isOwner = $currentUser && $demo->user_id === $currentUser->id;
        $isAdmin = $currentUser && isset($currentUser->admin) && $currentUser->admin;

        if (!($isOwner || $isAdmin)) {
            abort(403, 'Unauthorized');
        }

        if ($demo->record_id || in_array($demo->status, ['assigned', 'fallback-assigned'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete demo that is assigned to a record',
            ], 400);
        }

        // Intentionally no B2 file deletion — the app must never delete files
        // from Backblaze. The DB row is removed so the demo disappears from
        // the UI; the blob remains on B2 until the operator purges it manually.
        $demo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demo deleted successfully',
        ]);
    }

    /**
     * Get available maps for manual assignment
     */
    public function getMaps(Request $request)
    {
        $query = $request->get('search', '');

        $maps = \App\Models\Record::select('mapname')
            ->when($query, function ($q) use ($query) {
                return $q->where('mapname', 'like', '%' . $query . '%');
            })
            ->distinct()
            ->orderBy('mapname')
            ->limit(50)
            ->pluck('mapname');

        return response()->json($maps);
    }

    /**
     * Get available records for a specific map
     */
    public function getRecords(Request $request, $mapname)
    {
        $physics = $request->get('physics', 'VQ3');
        $gametype = 'run_' . strtolower($physics);

        $records = \App\Models\Record::where('mapname', $mapname)
            ->where('gametype', $gametype)
            ->with('user')
            ->orderBy('time')
            ->limit(100)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'time' => $record->time,
                    'formatted_time' => $this->formatTime($record->time),
                    'player_name' => $record->user ? $record->user->name : $record->name,
                    'rank' => $record->rank,
                    'date_set' => $record->date_set,
                ];
            });

        return response()->json($records);
    }

    /**
     * Manually assign demo to a record
     */
    public function assign(Request $request, UploadedDemo $demo)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            abort(403, 'You must be logged in');
        }

        $request->validate([
            'record_id' => 'required|exists:records,id',
        ]);

        $record = \App\Models\Record::findOrFail($request->record_id);
        $previousRecordId = $demo->record_id;

        Log::info('Demo assign', [
            'demo_id' => $demo->id,
            'record_id' => $record->id,
            'previous_record_id' => $previousRecordId,
            'by_user' => $currentUser->id,
            'by_user_name' => $currentUser->name,
            'is_owner' => $demo->user_id === $currentUser->id,
        ]);

        // Delete any existing offline_record (from fallback-assigned status)
        // When assigning to an online record, the demo should only appear in online demos, not offline
        if ($demo->offlineRecord) {
            $demo->offlineRecord->delete();
        }

        $demo->update([
            'record_id' => $record->id,
            'status' => 'assigned',
            'manually_assigned' => true,
        ]);

        // Update any linked RenderedVideo to point to the new record
        RenderedVideo::where('demo_id', $demo->id)->update(['record_id' => $record->id]);

        // Log manual assign in demo reports for admin visibility
        \App\Models\DemoAssignmentReport::create([
            'demo_id' => $demo->id,
            'report_type' => 'manual_assign',
            'reported_by_user_id' => $currentUser->id,
            'current_record_id' => $previousRecordId,
            'suggested_record_id' => $record->id,
            'reason_type' => 'manual_action',
            'reason_details' => $previousRecordId
                ? "Reassigned from record #{$previousRecordId} to #{$record->id}"
                : "Manually assigned to record #{$record->id}",
            'status' => 'resolved',
            'resolved_by_admin_id' => $currentUser->id,
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demo assigned successfully',
            'demo' => $demo->fresh()->load('record.user'),
        ]);
    }

    /**
     * Remove manual assignment from demo
     */
    public function unassign(UploadedDemo $demo)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            abort(403, 'You must be logged in');
        }

        Log::info('Demo unassign', [
            'demo_id' => $demo->id,
            'previous_record_id' => $demo->record_id,
            'by_user' => $currentUser->id,
            'by_user_name' => $currentUser->name,
            'is_owner' => $demo->user_id === $currentUser->id,
        ]);

        $previousRecordId = $demo->record_id;

        try {
            // Determine the correct status to restore
            $restoredStatus = 'processed';
            if ($demo->validity && $demo->validity !== 'valid') {
                $restoredStatus = 'failed-validity';
            } elseif ($demo->gametype && str_starts_with($demo->gametype, 'm')) {
                $restoredStatus = 'fallback-assigned';
            } else {
                $restoredStatus = 'fallback-assigned';
            }

            $demo->update([
                'record_id' => null,
                'status' => $restoredStatus,
                'manually_assigned' => false,
            ]);

            // Recreate OfflineRecord if it was deleted during assign
            if (!$demo->offlineRecord && $demo->map_name && $demo->time_ms) {
                $existingOffline = \App\Models\OfflineRecord::where('demo_id', $demo->id)->first();
                if (!$existingOffline) {
                    \App\Models\OfflineRecord::create([
                        'map_name' => $demo->map_name,
                        'physics' => strtoupper($demo->physics ?? ''),
                        'gametype' => $demo->gametype,
                        'time_ms' => $demo->time_ms,
                        'player_name' => $demo->player_name,
                        'demo_id' => $demo->id,
                        'date_set' => $demo->record_date ?? $demo->created_at,
                        'validity_flag' => $demo->validity !== 'valid' ? $demo->validity : null,
                    ]);
                }
            }

            // Clear record_id on linked RenderedVideo
            RenderedVideo::where('demo_id', $demo->id)->update(['record_id' => null]);

            // Log manual unassign in demo reports for admin visibility
            \App\Models\DemoAssignmentReport::create([
                'demo_id' => $demo->id,
                'report_type' => 'manual_unassign',
                'reported_by_user_id' => $currentUser->id,
                'current_record_id' => $previousRecordId,
                'reason_type' => 'manual_action',
                'reason_details' => "Unassigned from record #{$previousRecordId}",
                'status' => 'resolved',
                'resolved_by_admin_id' => $currentUser->id,
                'resolved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demo assignment removed',
                'demo' => $demo->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Demo unassign failed', ['demo_id' => $demo->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove assignment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Link/update YouTube video for a demo (admin only)
     */
    public function linkYoutube(Request $request, UploadedDemo $demo)
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->is_admin && !$currentUser->admin)) {
            abort(403, 'Admin only');
        }

        $request->validate([
            'youtube_video_id' => 'required|string|max:20',
        ]);

        $youtubeVideoId = $request->youtube_video_id;
        $youtubeUrl = "https://www.youtube.com/watch?v={$youtubeVideoId}";

        // Check if demo already has a RenderedVideo
        $existing = RenderedVideo::where('demo_id', $demo->id)->where('status', 'completed')->first();

        if ($existing) {
            $existing->update([
                'youtube_video_id' => $youtubeVideoId,
                'youtube_url' => $youtubeUrl,
            ]);
        } else {
            RenderedVideo::create([
                'map_name' => $demo->map_name,
                'player_name' => $demo->player_name,
                'physics' => $demo->physics,
                'time_ms' => $demo->time_ms,
                'gametype' => $demo->gametype,
                'record_id' => $demo->record_id,
                'demo_id' => $demo->id,
                'source' => 'manual',
                'status' => 'completed',
                'priority' => 3,
                'demo_url' => "https://defrag.racing/demos/{$demo->id}/download",
                'youtube_url' => $youtubeUrl,
                'youtube_video_id' => $youtubeVideoId,
                'is_visible' => true,
                'published_at' => now(),
                'publish_approved' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'youtube_video_id' => $youtubeVideoId,
        ]);
    }

    /**
     * Store uploaded demo LOCALLY in temp directory for processing
     * After processing, the compressed file will be uploaded to Backblaze
     */
    private function storeUploadedDemoLocally($file, $demoId)
    {
        $filename = $file->getClientOriginalName();

        // Store locally in temp directory using demo ID
        $directory = storage_path("app/demos/temp/{$demoId}");

        // Create directory
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Move uploaded file to temp directory
        $file->move($directory, $filename);

        // Return relative path from storage/app/
        return "demos/temp/{$demoId}/{$filename}";
    }

    /**
     * Format time from milliseconds to readable format
     */
    private function formatTime($timeMs)
    {
        $minutes = floor($timeMs / 60000);
        $seconds = floor(($timeMs % 60000) / 1000);
        $milliseconds = $timeMs % 1000;

        return sprintf('%02d:%02d.%03d', $minutes, $seconds, $milliseconds);
    }

    /**
     * Check if an extension is a supported archive
     */
    private function isArchiveExtension(string $ext): bool
    {
        return in_array(strtolower($ext), ['zip', 'rar', '7z']);
    }

    /**
     * Return storage directory for today's date (used for manual Storage::putFileAs calls)
     */
    private function storageDirForToday(): string
    {
        $date = now();
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');
        $hashPrefix = substr(md5((string) now()->timestamp), 0, 2);
        return "demos/uploaded/{$year}/{$month}/{$day}/{$hashPrefix}";
    }

    /**
     * Extract uploaded archive to a temp directory and return array of extracted demo file paths.
     * Supports zip natively (ZipArchive), and 7z/rar via system 7z binary if available.
     */
    private function extractArchiveToTemp($uploadedFile): array
    {
        $tmpDir = sys_get_temp_dir() . '/demos_extract_' . uniqid();
        if (!@mkdir($tmpDir, 0775, true)) {
            throw new \Exception('Unable to create temporary extraction directory');
        }

        $origPath = $uploadedFile->getPathname();
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        $extractedFiles = [];

        if ($ext === 'zip' && class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($origPath) === true) {
                // extract selectively: only files with .dm_\d+ extension
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $name = $stat['name'];
                    if (preg_match('/\.dm_\\d+$/i', $name)) {
                        $target = $tmpDir . '/' . basename($name);
                        copy('zip://' . $origPath . '#' . $name, $target);
                        $extractedFiles[] = $target;
                    }
                }
                $zip->close();
            } else {
                throw new \Exception('Failed to open ZIP archive');
            }
        } else {
            // Fallback: try 7z binary (supports rar/7z/zip). Only extract demo files.
            // Try several possible 7z/7za/7zr binaries that might exist on different systems
            $seven = trim(shell_exec('which 7z || which 7za || which 7zr || which p7zip || true'));
            if (!$seven) {
                // No extraction tool available
                throw new \Exception('Archive type not supported on server (missing zip extension or 7z binary)');
            }

            // Use -y to assume Yes on all queries; -p"" to never wait for password input; extract to tmpDir
            $cmd = escapeshellcmd($seven) . ' x ' . escapeshellarg($origPath) . ' -o' . escapeshellarg($tmpDir) . ' -y -p""';
            exec($cmd . ' 2>&1', $out, $rc);
            if ($rc !== 0) {
                // cleanup
                @unlink($origPath);
                $this->rrmdir($tmpDir);
                throw new \Exception('7z extraction failed: ' . implode('\n', $out));
            }

            // find extracted .dm_* files
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmpDir));
            foreach ($it as $file) {
                if ($file->isFile() && preg_match('/\.dm_\\d+$/i', $file->getFilename())) {
                    $extractedFiles[] = $file->getPathname();
                }
            }
        }

        return $extractedFiles;
    }

    /**
     * Clean up a failed demo: remove local files and delete DB record
     */
    private function cleanupFailedDemo(UploadedDemo $demo)
    {
        $failedDir = storage_path("app/demos/failed/{$demo->id}");
        if (is_dir($failedDir)) {
            $this->rrmdir($failedDir);
        }
        $demo->delete();
    }

    /**
     * Recursively remove directory
     */
    private function rrmdir($dir)
    {
        if (!is_dir($dir)) return;
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object === '.' || $object === '..') continue;
            $path = $dir . '/' . $object;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Detect archive by reading magic bytes of a file (zip, rar, 7z signatures)
     */
    private function isArchiveByContent(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) return false;

        $fp = fopen($path, 'rb');
        if (!$fp) return false;

        $bytes = fread($fp, 16);
        fclose($fp);

        if ($bytes === false) return false;

        // ZIP: 50 4B 03 04 or PK..
        if (strpos($bytes, "PK\x03\x04") !== false) return true;

        // 7z signature: 37 7A BC AF 27 1C (7z\xBC\xAF'\x1C)
        if (strpos($bytes, "\x37\x7A\xBC\xAF\x27\x1C") !== false) return true;

        // RAR signature: Rar!\x1A\x07\x00 (52 61 72 21 1A 07 00) or Rar!\x1A\x07\x01\x00
        if (strpos($bytes, "Rar!\x1A\x07\x00") !== false || strpos($bytes, "Rar!\x1A\x07\x01\x00") !== false) return true;

        return false;
    }
}