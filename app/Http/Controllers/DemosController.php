<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\UploadedDemo;
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
     * Display demo upload page
     */
    public function index()
    {
        // If user is authenticated, show their demos grouped by status
        if (Auth::check()) {
            $currentUser = Auth::user();
            $isAdmin = ($currentUser && ((isset($currentUser->is_admin) && $currentUser->is_admin) || (isset($currentUser->admin) && $currentUser->admin)));

            if ($isAdmin) {
                // Admin sees all uploads (including guest uploads)
                $userDemos = UploadedDemo::with(['record.user', 'user'])
                    ->orderByRaw("FIELD(status, 'assigned', 'processed', 'processing', 'pending', 'uploaded', 'failed')")
                    ->orderBy('created_at', 'desc')
                    ->paginate(30);
            } else {
                $userDemos = UploadedDemo::where('user_id', $currentUser->id)
                    ->with(['record.user'])
                    ->orderByRaw("FIELD(status, 'assigned', 'processed', 'processing', 'pending', 'uploaded', 'failed')")
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
            }
        } else {
            // For guests, show demos assigned to records AND publicly uploaded demos (user_id IS NULL)
            $userDemos = UploadedDemo::where(function ($q) {
                    $q->whereNotNull('record_id')
                      ->orWhereNull('user_id');
                })
                ->with(['record.user', 'user'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }

        return Inertia::render('Demos/Index', [
            'demos' => $userDemos,
        ]);
    }

    /**
     * Upload demos with rate limiting and queue processing
     */
    public function upload(Request $request)
    {
        // Rate limiting: allow a larger cap to support archive imports
        // Max demos allowed to be uploaded/processed per user per 5 minutes
        $RATE_LIMIT_MAX = 500;
    $userId = Auth::id();
        $rateLimitKey = "demo_upload_rate_limit_{$userId}";
        $currentUploads = Cache::get($rateLimitKey, 0);

        if ($currentUploads >= $RATE_LIMIT_MAX) {
            return response()->json([
                'success' => false,
                'message' => "Rate limit exceeded. You can upload maximum {$RATE_LIMIT_MAX} demos per 5 minutes.",
            ], 429);
        }

        $request->validate([
            'demos' => 'required|array|min:1|max:50', // Max 50 uploads per request (files or archives)
            // Allow larger files for archives; per-file max 512MB (512000 KB)
            'demos.*' => 'required|file|max:512000',
        ]);

        $uploadedDemos = [];
        $queuedDemos = [];
        $errors = [];
        $filesProcessed = 0;

        // Check total size limit (500MB total)
        $totalSize = 0;
        foreach ($request->file('demos') as $demoFile) {
            $totalSize += $demoFile->getSize();
        }

        if ($totalSize > 524288000) { // 500MB
            return response()->json([
                'success' => false,
                'message' => 'Total upload size exceeds 500MB limit.',
            ], 413);
        }

        // Process files in chunks of 10 for better memory management
        $demoFiles = $request->file('demos');
        $chunks = array_chunk($demoFiles, 10);

        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $index => $demoFile) {
                try {
                    $extension = strtolower($demoFile->getClientOriginalExtension());
                    $originalName = $demoFile->getClientOriginalName();

                    // Robust archive detection: check reported extension and original filename
                    $isArchiveExt = $this->isArchiveExtension($extension);
                    $isArchiveName = (bool) preg_match('/\.(zip|rar|7z)$/i', $originalName);
                    $isArchiveContent = false;
                    try {
                        $isArchiveContent = $this->isArchiveByContent($demoFile->getPathname());
                    } catch (\Throwable $t) {
                        Log::warning('Archive content detection failed', ['file' => $originalName, 'error' => $t->getMessage()]);
                    }

                    // Aggregate archive detection flags
                    $isArchive = $isArchiveExt || $isArchiveName || $isArchiveContent;

                    // Always log archive detection results for visibility
                    Log::info('Upload archive detection', [
                        'original_name' => $originalName,
                        'reported_extension' => $extension,
                        'isArchiveExt' => $isArchiveExt,
                        'isArchiveName' => $isArchiveName,
                        'isArchiveContent' => $isArchiveContent,
                        'client_path' => $demoFile->getPathname(),
                    ]);

                    // If the upload is an archive, extract and process contained demo files
                    if ($isArchive) {
                        try {
                            $extracted = $this->extractArchiveToTemp($demoFile);
                            if (empty($extracted)) {
                                $errors[] = $demoFile->getClientOriginalName() . ': Archive contained no valid demo files';
                                continue;
                            }

                            // Process each extracted file as a demo upload
                            foreach ($extracted as $extractedPath) {
                                if (!is_file($extractedPath)) continue;

                                // Calculate file hash and duplicates
                                $fileHash = md5_file($extractedPath);
                                $existingDemo = UploadedDemo::where('file_hash', $fileHash)->first();
                                if ($existingDemo) {
                                    $demoName = $existingDemo->processed_filename ?: $existingDemo->original_filename;
                                    $errors[] = basename($extractedPath) . ': Duplicate file content (already uploaded as: ' . $demoName . ')';
                                    @unlink($extractedPath);
                                    continue;
                                }

                                $originalName = basename($extractedPath);
                                $existingByFilename = UploadedDemo::where('user_id', $userId)
                                    ->where('original_filename', $originalName)
                                    ->first();
                                if ($existingByFilename) {
                                    $errors[] = $originalName . ': Filename already uploaded by you';
                                    @unlink($extractedPath);
                                    continue;
                                }

                                // Create database record first to get the ID
                                $demo = UploadedDemo::create([
                                    'original_filename' => $originalName,
                                    'file_path' => '', // Will be set after moving file
                                    'file_size' => filesize($extractedPath),
                                    'file_hash' => $fileHash,
                                    'user_id' => $userId,
                                    'status' => 'uploaded',
                                ]);

                                // Move extracted file to temp directory using demo ID
                                $directory = storage_path("app/demos/temp/{$demo->id}");
                                if (!is_dir($directory)) {
                                    mkdir($directory, 0755, true);
                                }
                                $destPath = $directory . '/' . $originalName;
                                rename($extractedPath, $destPath);
                                $storedPath = "demos/temp/{$demo->id}/{$originalName}";
                                $demo->update(['file_path' => $storedPath]);

                                // Dispatch for immediate processing (no long delay)
                                ProcessDemoJob::dispatch($demo);

                                $queuedDemos[] = $demo;
                                $filesProcessed++;

                                Log::info("Demo queued (from archive) for processing", [
                                    'demo_id' => $demo->id,
                                    'filename' => $demo->original_filename,
                                    'user_id' => $userId,
                                ]);

                                // cleanup extracted temp file
                                @unlink($extractedPath);
                            }

                            // remove extracted dir if empty (handled inside extractor)
                        } catch (\Exception $e) {
                            $errors[] = $demoFile->getClientOriginalName() . ': Archive processing failed - ' . $e->getMessage();
                            Log::error('Archive extraction failed', ['file' => $demoFile->getClientOriginalName(), 'error' => $e->getMessage()]);
                            continue;
                        }

                        continue; // move to next uploaded file
                    }

                    // Handle plain demo files (.dm_*)
                    if (!preg_match('/^dm_\d+$/', $extension)) {
                        $reason = 'extension_mismatch';
                        Log::warning('Demo rejected: invalid format', [
                            'file' => $demoFile->getClientOriginalName(),
                            'reported_extension' => $extension,
                            'reason' => $reason,
                            'client_path' => $demoFile->getPathname(),
                        ]);
                        $errors[] = $demoFile->getClientOriginalName() . ': Invalid demo file format';
                        continue;
                    }

                    // Calculate file hash for duplicate detection
                    $fileHash = md5_file($demoFile->getPathname());

                    // Check for duplicate file content (MD5 hash)
                    $existingDemo = UploadedDemo::where('file_hash', $fileHash)->first();
                    if ($existingDemo) {
                        $demoName = $existingDemo->processed_filename ?: $existingDemo->original_filename;
                        $errors[] = $demoFile->getClientOriginalName() . ': Duplicate file content (already uploaded as: ' . $demoName . ')';
                        continue;
                    }

                    // Also check for duplicate filename by same user
                    $existingByFilename = UploadedDemo::where('user_id', $userId)
                        ->where('original_filename', $demoFile->getClientOriginalName())
                        ->first();

                    if ($existingByFilename) {
                        $errors[] = $demoFile->getClientOriginalName() . ': Filename already uploaded by you';
                        continue;
                    }

                    // Create database record first to get the ID
                    $demo = UploadedDemo::create([
                        'original_filename' => $demoFile->getClientOriginalName(),
                        'file_path' => '', // Will be set after moving file
                        'file_size' => $demoFile->getSize(),
                        'file_hash' => $fileHash,
                        'user_id' => $userId,
                        'status' => 'uploaded',
                    ]);

                    // Store the uploaded file locally in temp directory using demo ID
                    $path = $this->storeUploadedDemoLocally($demoFile, $demo->id);
                    $demo->update(['file_path' => $path]);

                    // Dispatch immediately for processing
                    ProcessDemoJob::dispatch($demo);

                    $queuedDemos[] = $demo;
                    $filesProcessed++;

                    Log::info("Demo queued for processing", [
                        'demo_id' => $demo->id,
                        'filename' => $demo->original_filename,
                        'user_id' => $userId,
                    ]);

                } catch (\Exception $e) {
                    $errors[] = $demoFile->getClientOriginalName() . ': Upload failed - ' . $e->getMessage();
                    Log::error("Demo upload failed", [
                        'filename' => $demoFile->getClientOriginalName(),
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Memory cleanup after each chunk
            if ($chunkIndex % 3 === 0) {
                gc_collect_cycles();
            }
        }

        // Update rate limit counter
        Cache::put($rateLimitKey, $currentUploads + $filesProcessed, now()->addMinutes(5));

        return response()->json([
            'success' => true,
            'uploaded' => $queuedDemos,
            'errors' => $errors,
            'message' => count($queuedDemos) . ' demo(s) queued for processing' .
                        (count($errors) > 0 ? ', ' . count($errors) . ' failed' : ''),
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

        $filename = $demo->processed_filename ?: $demo->original_filename;

        // Check if demo is stored locally (failed or temp demos) or in Backblaze (processed demos)
        $isLocal = str_starts_with($demo->file_path, 'demos/temp/') ||
                   str_starts_with($demo->file_path, 'demos/failed/');

        if ($isLocal) {
            // Download from local storage
            $fullPath = storage_path("app/{$demo->file_path}");
            if (file_exists($fullPath)) {
                return response()->download($fullPath, $filename);
            } else {
                abort(404, 'Demo file not found');
            }
        }

        try {
            // Download from Backblaze (processed demos)
            // Use get() instead of download() to avoid size() metadata check that fails on Backblaze
            $fileContents = Storage::get($demo->file_path);

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

            // If the file does not exist anywhere, return 404
            Log::error('Demo file not found in Backblaze or local storage', [
                'demo_id' => $demo->id,
                'file_path' => $demo->file_path,
            ]);
            abort(404, 'Demo file not found');
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
                'time_ms' => null,
                'processing_output' => null,
                'record_id' => null,
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

        // If demo_ids provided, allow public polling for those specific demos (guest uploads)
        if ($demoIds && is_array($demoIds)) {
            $demos = UploadedDemo::whereIn('id', $demoIds)->with(['record.user'])->get();
            return response()->json([
                'processing_demos' => $demos->filter(function ($d) { return in_array($d->status, ['queued', 'processing']); })->values(),
                'queue_stats' => [
                    'total_queued' => UploadedDemo::where('status', 'queued')->count(),
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
        $processingQuery = UploadedDemo::whereIn('status', ['queued', 'processing'])->with(['record.user']);
        if (!$isAdmin) {
            $processingQuery->where('user_id', $userId);
        }
        $processingDemos = $processingQuery->get();

        // Get queue statistics
        $queueStats = [
            'total_queued' => UploadedDemo::where('status', 'queued')->count(),
            'total_processing' => UploadedDemo::where('status', 'processing')->count(),
            'user_queued' => $isAdmin ? UploadedDemo::where('status', 'queued')->count() : UploadedDemo::where('user_id', $userId)->where('status', 'queued')->count(),
            'user_processing' => $isAdmin ? UploadedDemo::where('status', 'processing')->count() : UploadedDemo::where('user_id', $userId)->where('status', 'processing')->count(),
        ];

        return response()->json([
            'processing_demos' => $processingDemos,
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

        if ($demo->record_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete demo that is assigned to a record',
            ], 400);
        }

        // Delete file
        Storage::delete($demo->file_path);

        // Delete database record
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
        $isAdmin = ($currentUser && ((isset($currentUser->is_admin) && $currentUser->is_admin) || (isset($currentUser->admin) && $currentUser->admin)));
        if (!$isAdmin && $demo->user_id !== optional($currentUser)->id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'record_id' => 'required|exists:records,id',
        ]);

        $record = \App\Models\Record::findOrFail($request->record_id);

        $demo->update([
            'record_id' => $record->id,
            'status' => 'assigned',
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
        $isAdmin = ($currentUser && ((isset($currentUser->is_admin) && $currentUser->is_admin) || (isset($currentUser->admin) && $currentUser->admin)));
        Log::info('Unassign attempt', ['demo_id' => $demo->id, 'current_user_id' => optional($currentUser)->id, 'is_admin' => $isAdmin]);

        if (!$isAdmin && $demo->user_id !== optional($currentUser)->id) {
            Log::warning('Unassign unauthorized', ['demo_id' => $demo->id, 'current_user_id' => optional($currentUser)->id]);
            abort(403, 'Unauthorized');
        }

        try {
            $demo->update([
                'record_id' => null,
                'status' => 'processed',
            ]);

            Log::info('Unassign successful', ['demo_id' => $demo->id, 'by_user' => optional($currentUser)->id]);

            return response()->json([
                'success' => true,
                'message' => 'Demo assignment removed',
                'demo' => $demo->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Unassign failed', ['demo_id' => $demo->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove assignment: ' . $e->getMessage(),
            ], 500);
        }
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

            // Use -y to assume Yes on all queries; extract to tmpDir
            $cmd = escapeshellcmd($seven) . ' x ' . escapeshellarg($origPath) . ' -o' . escapeshellarg($tmpDir) . ' -y';
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