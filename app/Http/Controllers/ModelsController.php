<?php

namespace App\Http\Controllers;

use App\Models\PlayerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use ZipArchive;

class ModelsController extends Controller
{
    /**
     * Display a listing of models
     */
    public function index(Request $request)
    {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        $category = $request->get('category', 'all');
        $sort = $request->get('sort', 'newest');
        $baseModel = $request->get('base_model');
        $authors = $request->get('authors');
        $search = $request->get('search');
        $myUploads = filter_var($request->get('my_uploads', false), FILTER_VALIDATE_BOOLEAN);
        $approvalStatus = $request->get('approval_status');
        $perPage = in_array((int) $request->input('per_page'), [12, 24, 48]) ? (int) $request->input('per_page') : 12;

        if (!$isPartial) {
            return Inertia::render('Models/Index', [
                'models' => null,
                'category' => $category,
                'sort' => $sort,
                'baseModel' => $baseModel,
                'authors' => $authors,
                'search' => $search,
                'myUploads' => $myUploads,
                'approvalStatus' => $approvalStatus,
                'perPage' => $perPage,
                'load_times' => [],
                'availableBaseModels' => null,
                'availableAuthors' => null,
                'hasUploads' => auth()->check() ? \App\Models\PlayerModel::where('user_id', auth()->id())->exists() : false,
            ]);
        }

        $totalStart = microtime(true);
        $timings = [];

        // Variables already parsed above, remove duplicates
        $category = $request->get('category', 'all');
        $sort = $request->get('sort', 'newest'); // newest or oldest
        $baseModel = $request->get('base_model'); // Filter by base model (comma-separated for multiple)
        $authors = $request->get('authors'); // Filter by authors (comma-separated for multiple)
        $search = $request->get('search'); // Search query
        $myUploads = filter_var($request->get('my_uploads', false), FILTER_VALIDATE_BOOLEAN); // Filter by user's uploads
        $approvalStatus = $request->get('approval_status'); // Filter by approval status (pending, approved, rejected)

        $start = microtime(true);
        $query = PlayerModel::with('user');

        // If viewing "My Uploads", filter by current user
        if ($myUploads && Auth::check()) {
            $query->where('user_id', Auth::id());

            // For "My Uploads", allow filtering by approval status
            if ($approvalStatus) {
                $query->approvalStatus($approvalStatus);
            }
        } else {
            // For public view, only show approved models
            $query->approved()->where('hidden', false);

            // Clear approval_status from URL if not viewing My Uploads
            // This prevents confusing URL states like approval_status=rejected with my_uploads=0
            $approvalStatus = null;
        }

        if ($category !== 'all') {
            $query->category($category);
        }

        // Filter by base model(s) if provided
        if ($baseModel) {
            $baseModels = array_filter(explode(',', $baseModel));
            if (count($baseModels) === 1) {
                $query->where('base_model', $baseModels[0]);
            } elseif (count($baseModels) > 1) {
                $query->whereIn('base_model', $baseModels);
            }
        }

        // Filter by author(s) if provided
        if ($authors) {
            $authorList = array_filter(explode(',', $authors));
            if (count($authorList) === 1) {
                $query->where('author', $authorList[0]);
            } elseif (count($authorList) > 1) {
                $query->whereIn('author', $authorList);
            }
        }

        // Search by name, author, or base model
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('author', 'LIKE', "%{$search}%")
                  ->orWhere('base_model', 'LIKE', "%{$search}%");
            });
        }

        // Apply sorting (use id as tiebreaker when created_at is same)
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'); // newest (default)
        }

        $perPage = in_array((int) $request->input('per_page'), [12, 24, 48]) ? (int) $request->input('per_page') : 12;
        $models = $query->paginate($perPage)->withQueryString();
        $timings['models_query'] = round((microtime(true) - $start) * 1000, 2);

        // Add resolved MD3 paths to each model for optimized rendering
        $start = microtime(true);
        $models->getCollection()->transform(function ($model) {
            // Determine the MD3 file path (for the 3D geometry)
            $md3FilePath = $this->getResolvedMd3Path($model);
            $model->resolved_md3_path = $md3FilePath;

            return $model;
        });
        $timings['path_resolution'] = round((microtime(true) - $start) * 1000, 2);

        $timings['total'] = round((microtime(true) - $totalStart) * 1000, 2);

        // Get available base models and authors for filter dropdowns
        $filterQuery = PlayerModel::approved()->where('hidden', false);
        if ($category !== 'all') {
            $filterQuery->category($category);
        }
        $availableBaseModels = (clone $filterQuery)
            ->whereNotNull('base_model')
            ->where('base_model', '!=', '')
            ->selectRaw('base_model, COUNT(*) as count')
            ->groupBy('base_model')
            ->orderBy('base_model')
            ->pluck('count', 'base_model');

        $availableAuthors = (clone $filterQuery)
            ->whereNotNull('author')
            ->where('author', '!=', '')
            ->selectRaw('author, COUNT(*) as count')
            ->groupBy('author')
            ->orderBy('author')
            ->pluck('count', 'author');

        return Inertia::render('Models/Index', [
            'models' => $models,
            'category' => $category,
            'sort' => $sort,
            'baseModel' => $baseModel,
            'authors' => $authors,
            'search' => $search,
            'myUploads' => $myUploads,
            'approvalStatus' => $approvalStatus,
            'perPage' => $perPage,
            'load_times' => $timings,
            'availableBaseModels' => $availableBaseModels,
            'availableAuthors' => $availableAuthors,
            'hasUploads' => auth()->check() ? \App\Models\PlayerModel::where('user_id', auth()->id())->exists() : false,
        ]);
    }

    /**
     * Get the resolved MD3 path for a model (for Index page thumbnails)
     */
    private function getResolvedMd3Path($model)
    {
        // Shadow models don't have MD3 files
        if ($model->category === 'shadow') {
            return null;
        }

        // For complete models, use their own file path
        if ($model->model_type === 'complete') {
            if (str_starts_with($model->file_path, 'baseq3/')) {
                return "/{$model->file_path}/head.md3";
            }
            // Check if file_path already includes full path (new format)
            if (stripos($model->file_path, '/models/players/') !== false) {
                return "/storage/{$model->file_path}/head.md3";
            }
            return "/storage/{$model->file_path}/models/players/{$model->base_model}/head.md3";
        }

        // For skin/mixed packs, use base_model_file_path if available
        if ($model->base_model_file_path) {
            if (str_starts_with($model->base_model_file_path, 'baseq3/')) {
                return "/{$model->base_model_file_path}/head.md3";
            }
            if (stripos($model->base_model_file_path, '/models/players/') !== false) {
                return "/storage/{$model->base_model_file_path}/head.md3";
            }
            return "/storage/{$model->base_model_file_path}/models/players/{$model->base_model}/head.md3";
        }

        // Fallback: assume it's a base Q3 model
        return "/baseq3/models/players/{$model->base_model}/head.md3";
    }

    /**
     * Show the form for creating a new model
     */
    public function create()
    {
        return Inertia::render('Models/Create');
    }

    /**
     * Store a newly created model
     *
     * Storage Architecture:
     * - Extracted files: storage/app/public/models/extracted/{slug}/ (web-accessible for 3D viewer)
     * - Original PK3s: storage/app/models/pk3s/{slug}.pk3 (private, served via download controller)
     * - Temp files: storage/app/models/temp/ (cleaned up after processing)
     */

    /**
     * Step 1 of upload flow: Temporarily upload and extract a model file.
     * Returns JSON with detected models for the frontend to display in 3D viewer + generate GIFs.
     */
    public function tempUpload(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:player,weapon,shadow',
            'model_file' => 'required|file|mimes:zip,pk3|max:51200',
            'is_nsfw' => 'nullable|boolean',
        ]);

        $modelName = $request->name;
        $slug = \Str::slug($modelName) . '-' . time();

        // Extract to public storage so 3D viewer can access files
        $extractPath = storage_path('app/public/models/extracted/' . $slug);

        // Store original file temporarily
        $uploadedFile = $request->file('model_file');
        $tempPath = $uploadedFile->storeAs('models/temp', $slug . '.' . $uploadedFile->getClientOriginalExtension(), 'local');
        $tempFullPath = storage_path('app/' . $tempPath);

        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        // Extract using shared logic
        $extractResult = $this->extractUploadedArchive($tempFullPath, $extractPath, $slug);

        // Clean up temp uploaded file
        Storage::disk('local')->delete($tempPath);

        if (!$extractResult['success']) {
            $this->deleteDirectory($extractPath);
            return response()->json(['success' => false, 'message' => $extractResult['message']], 422);
        }

        // Check for __pk3_* subdirectories (multi-PK3 ZIP upload)
        $pk3Subdirs = [];
        if (is_dir($extractPath)) {
            foreach (scandir($extractPath) as $entry) {
                if (preg_match('/^__pk3_(\d+)_(.+)$/', $entry, $matches) && is_dir($extractPath . '/' . $entry)) {
                    $pk3Subdirs[] = [
                        'dir' => $entry,
                        'index' => (int) $matches[1],
                        'name' => $matches[2],
                        'path' => $extractPath . '/' . $entry,
                    ];
                }
            }
        }

        // Detect models
        $models = [];

        if ($request->category === 'weapon' && !empty($pk3Subdirs)) {
            // Multi-PK3 weapon upload: scan each PK3 subdirectory separately
            foreach ($pk3Subdirs as $pk3Sub) {
                $weaponNames = $this->detectAllWeaponNames($pk3Sub['path']);
                foreach ($weaponNames as $weaponName) {
                    $weaponModel = $this->buildWeaponModelInfo(
                        $pk3Sub['path'], $weaponName, $slug,
                        $pk3Sub['dir'], $pk3Sub['name'],
                        'models/pk3s/' . $slug . '_' . $pk3Sub['index'] . '.pk3',
                        true // isMultiPk3
                    );
                    if ($weaponModel) {
                        $models[] = $weaponModel;
                    }
                }
            }
        } elseif ($request->category === 'weapon') {
            // Single PK3 weapon upload
            $detectedModelNames = $this->detectAllWeaponNames($extractPath);
            $isMultiModelPack = count($detectedModelNames) > 1;
            foreach ($detectedModelNames as $detectedModelName) {
                $weaponModel = $this->buildWeaponModelInfo(
                    $extractPath, $detectedModelName, $slug,
                    null, null,
                    $extractResult['pk3_path'],
                    $isMultiModelPack
                );
                if ($weaponModel) {
                    $models[] = $weaponModel;
                }
            }
        } elseif ($request->category === 'shadow' && !empty($pk3Subdirs)) {
            // Multi-PK3 shadow upload
            foreach ($pk3Subdirs as $pk3Sub) {
                $shadowModel = $this->buildShadowModelInfo(
                    $pk3Sub['path'], $slug,
                    $pk3Sub['dir'], $pk3Sub['name'],
                    'models/pk3s/' . $slug . '_' . $pk3Sub['index'] . '.pk3'
                );
                if ($shadowModel) {
                    $models[] = $shadowModel;
                }
            }
        } elseif ($request->category === 'shadow') {
            // Single PK3 shadow upload
            $shadowModel = $this->buildShadowModelInfo(
                $extractPath, $slug,
                null, null,
                $extractResult['pk3_path']
            );
            if ($shadowModel) {
                $models[] = $shadowModel;
            }
        } else {
            // Player models
            $detectedModelNames = $this->detectAllModelNames($extractPath);
            $isMultiModelPack = count($detectedModelNames) > 1;

            foreach ($detectedModelNames as $detectedModelName) {
                $metadata = $this->parseModelMetadata($extractPath, $detectedModelName);
                $hasMd3Files = $this->checkForMd3Files($extractPath, $detectedModelName);
                $availableSkins = $metadata['available_skins'] ?? ['default'];

                foreach ($availableSkins as $skinName) {
                    if ($isMultiModelPack) {
                        $finalName = ucfirst($detectedModelName) . ' (' . $skinName . ')';
                    } else {
                        $finalName = count($availableSkins) > 1
                            ? $modelName . ' (' . $skinName . ')'
                            : $modelName;
                    }

                    $filePath = 'models/extracted/' . $slug;
                    $viewerBasePath = '/storage/' . $filePath;

                    // Determine viewer path (head.md3)
                    $playerPath = $extractPath . '/models/players/' . $detectedModelName;
                    $viewerPath = null;
                    if (is_dir($playerPath)) {
                        $viewerPath = $viewerBasePath . '/models/players/' . $detectedModelName . '/head.md3';
                    }

                    $skinPath = null;
                    if ($viewerPath) {
                        $skinPath = $viewerBasePath . '/models/players/' . $detectedModelName . '/head_' . $skinName . '.skin';
                    }

                    $models[] = [
                        'detected_name' => $detectedModelName,
                        'skin_name' => $skinName,
                        'display_name' => $finalName,
                        'category' => $request->category,
                        'available_skins' => [$skinName],
                        'file_path' => $filePath,
                        'has_md3' => $hasMd3Files,
                        'model_type' => $this->determineModelType($extractPath, $detectedModelName, $hasMd3Files, $metadata),
                        'viewer_path' => $viewerPath,
                        'skin_path' => $skinPath,
                        'base_model' => $detectedModelName,
                    ];
                }
            }
        }

        if (empty($models)) {
            $this->deleteDirectory($extractPath);
            $errorMsgs = [
                'weapon' => 'Could not find any weapon models. Make sure the PK3 contains models/weapons2/{name}/ directories.',
                'shadow' => 'Could not find any shadow files. Make sure the PK3 contains gfx/misc/shadow or gfx/damage/shadow textures.',
                'player' => 'Could not find any model folders. Make sure the PK3 contains models/players/{name}/ directories.',
            ];
            $errorMsg = $errorMsgs[$request->category] ?? $errorMsgs['player'];
            return response()->json(['success' => false, 'message' => $errorMsg], 422);
        }

        // Store temp upload metadata in session for step 2
        $tempData = [
            'slug' => $slug,
            'name' => $modelName,
            'description' => $request->description,
            'category' => $request->category,
            'is_nsfw' => $request->is_nsfw ?? false,
            'author' => $request->author ?: null,
            'pk3_path' => $extractResult['pk3_path'],
            'extract_path' => $extractPath,
            'models' => $models,
            'created_at' => now()->timestamp,
        ];

        session()->put('temp_upload_' . $slug, $tempData);

        return response()->json([
            'success' => true,
            'slug' => $slug,
            'models' => $models,
        ]);
    }

    /**
     * Step 2 of upload flow: Save model(s) with GIF thumbnails.
     * Called after frontend has generated GIFs for each detected model.
     */
    public function storeWithGifs(Request $request)
    {
        $request->validate([
            'slug' => 'required|string',
            'gifs' => 'required|array',
            'gifs.*.model_index' => 'required|integer|min:0',
            'gifs.*.rotate_gif' => 'nullable|file|mimes:gif|max:10240',
            'gifs.*.idle_gif' => 'nullable|file|mimes:gif|max:10240',
            'gifs.*.gesture_gif' => 'nullable|file|mimes:gif|max:10240',
            'gifs.*.head_icon' => 'nullable|file|mimes:png,jpg,jpeg|max:1024',
            'gifs.*.thumbnail' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        $slug = $request->slug;
        $tempData = session()->get('temp_upload_' . $slug);

        if (!$tempData) {
            return response()->json(['success' => false, 'message' => 'Upload session expired. Please start over.'], 422);
        }

        // Check session age (max 30 min)
        if (now()->timestamp - $tempData['created_at'] > 1800) {
            session()->forget('temp_upload_' . $slug);
            $this->deleteDirectory($tempData['extract_path']);
            if ($tempData['pk3_path'] && file_exists(storage_path('app/' . $tempData['pk3_path']))) {
                unlink(storage_path('app/' . $tempData['pk3_path']));
            }
            return response()->json(['success' => false, 'message' => 'Upload session expired (30 min). Please start over.'], 422);
        }

        $userId = Auth::id();
        $category = $tempData['category'];
        $extractPath = $tempData['extract_path'];
        $pk3Path = $tempData['pk3_path'];
        $detectedModels = $tempData['models'];
        $isMultiModelPack = count($detectedModels) > 1;

        // Thumbnails directory
        $thumbnailsDir = storage_path('app/public/thumbnails');
        if (!file_exists($thumbnailsDir)) {
            mkdir($thumbnailsDir, 0755, true);
        }

        $createdModels = [];

        // Build a map of GIF files by model_index
        $gifsByIndex = [];
        if ($request->has('gifs')) {
            foreach ($request->file('gifs', []) as $idx => $gifFiles) {
                $modelIndex = $request->input("gifs.{$idx}.model_index", $idx);
                $gifsByIndex[$modelIndex] = $gifFiles;
            }
        }

        foreach ($detectedModels as $idx => $modelInfo) {
            if ($category === 'weapon') {
                // For multi-PK3 weapons, use the per-PK3 subdirectory as search path
                $weaponSearchPath = $extractPath;
                if (!empty($modelInfo['pk3_subdir'])) {
                    $weaponSearchPath = $extractPath . '/' . $modelInfo['pk3_subdir'];
                }
                $metadata = $this->parseWeaponMetadata($weaponSearchPath, $modelInfo['detected_name']);

                // Use per-model pk3_path if available (multi-PK3), otherwise global
                $modelPk3Path = $modelInfo['pk3_path'] ?? $pk3Path;

                $model = PlayerModel::create([
                    'user_id' => $userId,
                    'name' => $modelInfo['display_name'],
                    'base_model' => $modelInfo['detected_name'],
                    'main_file' => $modelInfo['main_file'],
                    'model_type' => 'complete',
                    'description' => $tempData['description'],
                    'category' => 'weapon',
                    'author' => $tempData['author'] ?? $metadata['author'] ?? null,
                    'author_email' => $metadata['author_email'] ?? null,
                    'file_path' => $modelInfo['file_path'],
                    'zip_path' => $modelPk3Path,
                    'poly_count' => $metadata['poly_count'] ?? null,
                    'vert_count' => $metadata['vert_count'] ?? null,
                    'has_sounds' => false,
                    'has_ctf_skins' => false,
                    'available_skins' => json_encode($modelInfo['available_skins']),
                    'approval_status' => 'pending',
                    'is_nsfw' => $tempData['is_nsfw'],
                ]);
            } elseif ($category === 'shadow') {
                $modelPk3Path = $modelInfo['pk3_path'] ?? $pk3Path;

                $model = PlayerModel::create([
                    'user_id' => $userId,
                    'name' => $modelInfo['display_name'],
                    'base_model' => 'shadow',
                    'main_file' => null,
                    'model_type' => 'complete',
                    'description' => $tempData['description'],
                    'category' => 'shadow',
                    'author' => $tempData['author'] ?? null,
                    'file_path' => $modelInfo['file_path'],
                    'zip_path' => $modelPk3Path,
                    'has_sounds' => false,
                    'has_ctf_skins' => false,
                    'available_skins' => json_encode(['default']),
                    'approval_status' => 'pending',
                    'is_nsfw' => $tempData['is_nsfw'],
                ]);
            } else {
                $metadata = $this->parseModelMetadata($extractPath, $modelInfo['detected_name']);
                $hasMd3Files = $modelInfo['has_md3'] ?? false;
                $baseModel = $modelInfo['base_model'] ?? $modelInfo['detected_name'];
                $baseModelFilePath = $this->determineBaseModelFilePath($extractPath, $modelInfo['detected_name'], $hasMd3Files, $baseModel);
                $modelType = $modelInfo['model_type'] ?? 'complete';
                $skinName = $modelInfo['skin_name'] ?? 'default';

                $model = PlayerModel::create([
                    'user_id' => $userId,
                    'name' => $modelInfo['display_name'],
                    'base_model' => $baseModel,
                    'base_model_file_path' => $baseModelFilePath,
                    'model_type' => $modelType,
                    'description' => $tempData['description'],
                    'category' => $category,
                    'author' => $tempData['author'] ?? $metadata['author'] ?? null,
                    'author_email' => $metadata['author_email'] ?? null,
                    'file_path' => $modelInfo['file_path'],
                    'zip_path' => $pk3Path,
                    'poly_count' => $metadata['poly_count'] ?? null,
                    'vert_count' => $metadata['vert_count'] ?? null,
                    'has_sounds' => $metadata['has_sounds'] ?? false,
                    'has_ctf_skins' => $metadata['has_ctf_skins'] ?? false,
                    'available_skins' => json_encode($modelInfo['available_skins']),
                    'approval_status' => 'pending',
                    'is_nsfw' => $tempData['is_nsfw'],
                ]);
            }

            // Save GIF files for this model
            $gifFiles = $gifsByIndex[$idx] ?? [];
            $updateData = [];

            $gifTypes = [
                'rotate_gif' => "model_{$model->id}_rotate.gif",
                'idle_gif' => "model_{$model->id}_idle.gif",
                'gesture_gif' => "model_{$model->id}_gesture.gif",
            ];

            foreach ($gifTypes as $field => $filename) {
                if (isset($gifFiles[$field]) && $gifFiles[$field]->isValid()) {
                    $gifFiles[$field]->storeAs('public/thumbnails', $filename, 'local');
                    $updateData[$field] = "thumbnails/{$filename}";
                }
            }

            if (isset($gifFiles['thumbnail']) && $gifFiles['thumbnail']->isValid()) {
                $thumbFilename = "model_{$model->id}_still.png";
                $gifFiles['thumbnail']->storeAs('public/thumbnails', $thumbFilename, 'local');
                $updateData['thumbnail'] = "thumbnails/{$thumbFilename}";
            }

            if (isset($gifFiles['head_icon']) && $gifFiles['head_icon']->isValid()) {
                $headFilename = "model_{$model->id}_head.png";
                $gifFiles['head_icon']->storeAs('public/thumbnails', $headFilename, 'local');
                $updateData['head_icon'] = "thumbnails/{$headFilename}";
            }

            if (!empty($updateData)) {
                $model->update($updateData);
            }

            $createdModels[] = $model;
        }

        // Clean up session
        session()->forget('temp_upload_' . $slug);

        if (empty($createdModels)) {
            return response()->json(['success' => false, 'message' => 'No models were created.'], 422);
        }

        $successMessage = count($createdModels) > 1
            ? sprintf('Successfully uploaded %d model variations. They will be visible once approved.',
                count($createdModels))
            : sprintf('Model "%s" uploaded successfully! It will be visible once approved.', $createdModels[0]->name);

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'redirect' => route('models.show', $createdModels[0]->id),
            'model_ids' => array_map(fn($m) => $m->id, $createdModels),
        ]);
    }

    /**
     * Delete a temporary upload (cleanup).
     */
    public function deleteTempUpload(Request $request)
    {
        $slug = $request->input('slug');
        if (!$slug) {
            return response()->json(['success' => false], 400);
        }

        $tempData = session()->get('temp_upload_' . $slug);
        if ($tempData) {
            // Clean up extracted files
            if (isset($tempData['extract_path']) && is_dir($tempData['extract_path'])) {
                $this->deleteDirectory($tempData['extract_path']);
            }
            // Clean up PK3
            if (isset($tempData['pk3_path']) && $tempData['pk3_path'] && file_exists(storage_path('app/' . $tempData['pk3_path']))) {
                unlink(storage_path('app/' . $tempData['pk3_path']));
            }
            session()->forget('temp_upload_' . $slug);
        } else {
            // Fallback: try to clean up by slug even without session
            $extractPath = storage_path('app/public/models/extracted/' . $slug);
            if (is_dir($extractPath)) {
                $this->deleteDirectory($extractPath);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Extract an uploaded archive (ZIP or PK3) to the extract path.
     * Returns ['success' => bool, 'message' => string, 'pk3_path' => string|null]
     */
    private function extractUploadedArchive($tempFullPath, $extractPath, $slug)
    {
        $pk3StoragePath = storage_path('app/models/pk3s');
        if (!file_exists($pk3StoragePath)) {
            mkdir($pk3StoragePath, 0755, true);
        }

        $zip = new ZipArchive;
        $pk3PathForDownload = null;

        if ($zip->open($tempFullPath) !== TRUE) {
            return ['success' => false, 'message' => 'Failed to open archive file.', 'pk3_path' => null];
        }

        // Check if this is a ZIP containing PK3 files
        $pk3FileNames = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $basename = basename($filename);
            $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            $hasSlash = strpos($filename, '/');

            if ($ext === 'pk3' && $hasSlash === false) {
                $pk3FileNames[] = $filename;
            }
        }

        if (!empty($pk3FileNames)) {
            // ZIP containing PK3 file(s)
            $tempExtract = storage_path('app/models/temp/' . $slug . '_extract');
            if (!file_exists($tempExtract)) {
                mkdir($tempExtract, 0755, true);
            }

            $zip->extractTo($tempExtract);
            $zip->close();

            if (count($pk3FileNames) === 1) {
                // Single PK3 in ZIP — extract directly
                $pk3File = $tempExtract . '/' . $pk3FileNames[0];
                if (file_exists($pk3File)) {
                    $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                    copy($pk3File, storage_path('app/' . $pk3PathForDownload));

                    $pk3Zip = new ZipArchive;
                    if ($pk3Zip->open($pk3File) === TRUE) {
                        $pk3Zip->extractTo($extractPath);
                        $pk3Zip->close();
                    }
                    $this->generateFileManifest($extractPath);
                }
            } else {
                // Multiple PK3s — extract each into its own subdirectory
                // so they don't overwrite each other's files
                foreach ($pk3FileNames as $idx => $pk3FileName) {
                    $pk3File = $tempExtract . '/' . $pk3FileName;
                    if (!file_exists($pk3File)) continue;

                    $pk3Slug = pathinfo($pk3FileName, PATHINFO_FILENAME);
                    $subExtractPath = $extractPath . '/__pk3_' . $idx . '_' . $pk3Slug;
                    if (!file_exists($subExtractPath)) {
                        mkdir($subExtractPath, 0755, true);
                    }

                    // Store each PK3 separately for downloads
                    $pk3StorePath = 'models/pk3s/' . $slug . '_' . $idx . '.pk3';
                    copy($pk3File, storage_path('app/' . $pk3StorePath));

                    $pk3Zip = new ZipArchive;
                    if ($pk3Zip->open($pk3File) === TRUE) {
                        $pk3Zip->extractTo($subExtractPath);
                        $pk3Zip->close();
                    }
                    $this->mergeCaseDuplicateDirs($subExtractPath);
                    $this->generateFileManifest($subExtractPath);
                }

                // Use first PK3 path as default (will be overridden per-model)
                $pk3PathForDownload = 'models/pk3s/' . $slug . '_0.pk3';
            }

            $this->deleteDirectory($tempExtract);
        } else {
            // Direct PK3 file
            $hasProperStructure = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (stripos($filename, 'models/players/') === 0 ||
                    stripos($filename, 'models/weapons2/') === 0 ||
                    stripos($filename, 'sound/player/') === 0 ||
                    stripos($filename, 'gfx/misc/shadow') === 0 ||
                    stripos($filename, 'gfx/damage/shadow') === 0 ||
                    stripos($filename, 'scripts/shadow') === 0) {
                    $hasProperStructure = true;
                    break;
                }
            }

            if ($hasProperStructure) {
                $zip->extractTo($extractPath);
                $zip->close();
                $this->generateFileManifest($extractPath);

                $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                copy($tempFullPath, storage_path('app/' . $pk3PathForDownload));
            } else {
                $zip->close();
                return ['success' => false, 'message' => 'Invalid PK3 structure. Must contain models/players/, models/weapons2/, or gfx/misc/shadow files.', 'pk3_path' => null];
            }
        }

        if (!$pk3PathForDownload) {
            return ['success' => false, 'message' => 'Failed to extract model file.', 'pk3_path' => null];
        }

        // Merge case-duplicate directories (e.g. scripts/ and SCRIPTS/ → lowercase wins)
        $this->mergeCaseDuplicateDirs($extractPath);

        return ['success' => true, 'message' => 'OK', 'pk3_path' => $pk3PathForDownload];
    }

    /**
     * Merge directories that differ only in case (e.g. SCRIPTS/ and scripts/).
     * Moves all files from the uppercase variant into the lowercase one, then removes it.
     * Only operates on top-level directories of the extract path.
     */
    private function mergeCaseDuplicateDirs($extractPath, $depth = 0)
    {
        // Limit recursion depth to prevent infinite loops
        if ($depth > 10) return;

        $entries = scandir($extractPath);
        if (!$entries) return;

        // Group directories by lowercase name
        $groups = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (!is_dir($extractPath . '/' . $entry)) continue;
            $lower = strtolower($entry);
            $groups[$lower][] = $entry;
        }

        foreach ($groups as $lower => $dirs) {
            if (count($dirs) > 1) {
                // Pick the lowercase name as the canonical target
                $target = $lower;
                $targetPath = $extractPath . '/' . $target;

                // If the lowercase dir doesn't exist yet, rename one of the existing ones
                if (!is_dir($targetPath)) {
                    rename($extractPath . '/' . $dirs[0], $targetPath);
                    array_shift($dirs);
                }

                // Merge remaining dirs into target
                foreach ($dirs as $dir) {
                    $dirPath = $extractPath . '/' . $dir;
                    if ($dirPath === $targetPath) continue;
                    if (!is_dir($dirPath)) continue;

                    $this->mergeDirRecursive($dirPath, $targetPath);
                    $this->deleteDirectory($dirPath);
                }
            }
        }

        // Recurse into all remaining subdirectories to merge nested case duplicates
        $entries = scandir($extractPath);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir($extractPath . '/' . $entry)) {
                $this->mergeCaseDuplicateDirs($extractPath . '/' . $entry, $depth + 1);
            }
        }
    }

    /**
     * Recursively merge $source directory into $target.
     * Files in source that don't exist in target are moved; conflicts are skipped (target wins).
     */
    private function mergeDirRecursive($source, $target)
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $entries = scandir($source);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $srcPath = $source . '/' . $entry;
            $dstPath = $target . '/' . $entry;

            if (is_dir($srcPath)) {
                $this->mergeDirRecursive($srcPath, $dstPath);
            } else {
                if (!file_exists($dstPath)) {
                    rename($srcPath, $dstPath);
                }
                // If target file already exists, skip (target wins)
            }
        }
    }

    /**
     * Upload and create model(s) from a PK3/ZIP file (legacy single-step flow).
     *
     * Storage Architecture:
     * - Extracted files: storage/app/public/models/extracted/{slug}/ (web-accessible for 3D viewer)
     * - Original PK3s: storage/app/models/pk3s/{slug}.pk3 (private, served via download controller)
     * - Temp files: storage/app/models/temp/ (cleaned up after processing)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:player,weapon,shadow',
            'model_file' => 'required|file|mimes:zip,pk3|max:51200', // 50MB max - ZIP or PK3 files
            'is_nsfw' => 'nullable|boolean',
        ]);

        $uploadedFile = $request->file('model_file');
        $userId = Auth::id();
        $modelName = $request->name;
        $slug = \Str::slug($modelName) . '-' . time();

        // Extracted files go to PUBLIC storage (web-accessible for 3D viewer)
        $extractPath = storage_path('app/public/models/extracted/' . $slug);

        // Original PK3 goes to PRIVATE storage (not web-accessible)
        $pk3StoragePath = storage_path('app/models/pk3s');
        if (!file_exists($pk3StoragePath)) {
            mkdir($pk3StoragePath, 0755, true);
        }

        // Store original file temporarily in private storage (must be local for ZipArchive)
        $tempPath = $uploadedFile->storeAs('models/temp', $slug . '.' . $uploadedFile->getClientOriginalExtension(), 'local');
        $tempFullPath = storage_path('app/' . $tempPath);

        // Create extraction directory
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $zip = new ZipArchive;
        $pk3Found = false;
        $pk3PathForDownload = null;

        // Check if uploaded file is ZIP or PK3
        if ($zip->open($tempFullPath) === TRUE) {
            // Check if this is a ZIP containing PK3 files
            $pk3FileNames = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $basename = basename($filename);
                $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                $hasSlash = strpos($filename, '/');

                if ($ext === 'pk3' && $hasSlash === false) {
                    $pk3FileNames[] = $filename;
                }
            }

            if (!empty($pk3FileNames)) {
                // ZIP containing PK3 file(s) — extract all into shared directory
                $tempExtract = storage_path('app/models/temp/' . $slug . '_extract');
                if (!file_exists($tempExtract)) {
                    mkdir($tempExtract, 0755, true);
                }

                $zip->extractTo($tempExtract);
                $zip->close();

                foreach ($pk3FileNames as $pk3FileName) {
                    $pk3File = $tempExtract . '/' . $pk3FileName;
                    if (file_exists($pk3File)) {
                        $pk3Zip = new ZipArchive;
                        if ($pk3Zip->open($pk3File) === TRUE) {
                            $pk3Zip->extractTo($extractPath);
                            $pk3Zip->close();
                        }
                    }
                }

                $pk3Found = true;
                $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                copy($tempFullPath, storage_path('app/' . $pk3PathForDownload));
                $this->generateFileManifest($extractPath);

                $this->deleteDirectory($tempExtract);
            } else {
                // This is a direct PK3 file - check if it has the proper structure
                $hasProperStructure = false;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (stripos($filename, 'models/players/') === 0 ||
                        stripos($filename, 'models/weapons2/') === 0 ||
                        stripos($filename, 'sound/player/') === 0 ||
                        stripos($filename, 'gfx/misc/shadow') === 0 ||
                        stripos($filename, 'gfx/damage/shadow') === 0 ||
                        stripos($filename, 'scripts/shadow') === 0) {
                        $hasProperStructure = true;
                        break;
                    }
                }

                if ($hasProperStructure) {
                    // This is a direct PK3 file with proper structure
                    $zip->extractTo($extractPath);
                    $zip->close();
                    $pk3Found = true;
                    $this->generateFileManifest($extractPath);

                    // Store the PK3 in PRIVATE storage for downloads
                    $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                    copy($tempFullPath, storage_path('app/' . $pk3PathForDownload));
                } else {
                    $zip->close();
                }
            }

            // Clean up temp file
            Storage::disk('local')->delete($tempPath);

            if ($pk3Found) {
                // Auto-detect ALL model names (PK3 might contain multiple models)
                // Detection method depends on the category selected
                if ($request->category === 'weapon') {
                    $detectedModelNames = $this->detectAllWeaponNames($extractPath);
                    \Log::info('Detected weapon models:', ['names' => $detectedModelNames]);
                } else {
                    $detectedModelNames = $this->detectAllModelNames($extractPath);
                }

                if (empty($detectedModelNames)) {
                    $this->deleteDirectory($extractPath);
                    $errorMsg = $request->category === 'weapon'
                        ? 'Could not find any weapon models. Make sure the PK3 contains models/weapons2/{name}/ directories.'
                        : 'Could not find any model folders. Make sure the PK3 contains models/players/{name}/ directories.';
                    return back()->with('error', $errorMsg);
                }

                $createdModels = [];
                $isMultiModelPack = count($detectedModelNames) > 1;

                // Create a separate database entry for each model+skin combination
                foreach ($detectedModelNames as $detectedModelName) {
                    if ($request->category === 'weapon') {
                        // WEAPON MODEL PROCESSING
                        \Log::info('Processing weapon:', ['name' => $detectedModelName]);
                        $weaponsBase = $this->findWeaponsPath($extractPath);
                        $weaponPath = ($weaponsBase ?: $extractPath . '/models/weapons2') . '/' . $detectedModelName;

                        // Find MD3 files for this weapon
                        $md3Files = glob($weaponPath . '/*.md3');

                        // MD3 files are OPTIONAL - texture/shader-only packs are valid!
                        // They will fallback to base Q3 weapon models
                        $mainMd3 = null;

                        if (!empty($md3Files)) {
                            // Find the main weapon MD3 file (similar to ImportBaseWeapons command)
                            foreach ($md3Files as $md3File) {
                                $basename = basename($md3File, '.md3');
                                // Look for the base file without suffixes like _1, _2, _hand, _flash, _barrel
                                if ($basename === $detectedModelName) {
                                    $mainMd3 = basename($md3File);
                                    break;
                                }
                            }

                            // If no exact match, use the first MD3 file that's not a variant
                            if (!$mainMd3) {
                                foreach ($md3Files as $md3File) {
                                    $basename = basename($md3File, '.md3');
                                    if (!preg_match('/_(hand|flash|barrel|[0-9])$/', $basename)) {
                                        $mainMd3 = basename($md3File);
                                        break;
                                    }
                                }
                            }

                            // Last resort: use first MD3 file
                            if (!$mainMd3) {
                                $mainMd3 = basename($md3Files[0]);
                            }

                            \Log::info("Found MD3 file for weapon {$detectedModelName}: {$mainMd3}");
                        } else {
                            \Log::info("No MD3 files found for weapon {$detectedModelName} - will use fallback to base Q3 model");
                        }

                        // Find ALL shader files in scripts directory
                        $scriptsPath = $this->findScriptsPath($extractPath);
                        $shaderFiles = [];
                        if ($scriptsPath && is_dir($scriptsPath)) {
                            // Get all .shader and .shaderx files
                            $allShaders = array_merge(
                                glob($scriptsPath . '/*.shader') ?: [],
                                glob($scriptsPath . '/*.shaderx') ?: []
                            );
                            // Store just the filenames
                            foreach ($allShaders as $shaderFile) {
                                $shaderFiles[] = basename($shaderFile);
                            }
                            \Log::info("Found shader files for weapon {$detectedModelName}:", $shaderFiles);
                        }

                        // Get available skins (if any)
                        $skinFiles = glob($weaponPath . '/*.skin');
                        $availableSkins = [];

                        foreach ($skinFiles as $skinFile) {
                            $skinBasename = basename($skinFile, '.skin');
                            // Extract skin name (e.g., weapon_red.skin -> red)
                            if (str_starts_with($skinBasename, $detectedModelName . '_')) {
                                $skinName = str_replace($detectedModelName . '_', '', $skinBasename);
                            } else {
                                $skinName = $skinBasename;
                            }

                            if (!in_array($skinName, $availableSkins)) {
                                $availableSkins[] = $skinName;
                            }
                        }

                        if (empty($availableSkins)) {
                            $availableSkins = ['default'];
                        }

                        // Parse metadata from README if present
                        $metadata = $this->parseWeaponMetadata($extractPath, $detectedModelName);

                        // Determine final name
                        $finalModelName = $isMultiModelPack
                            ? ucfirst($detectedModelName)
                            : $modelName;

                        // Create weapon model record
                        \Log::info('Creating weapon model in database:', [
                            'name' => $finalModelName,
                            'base_model' => $detectedModelName,
                            'main_file' => $mainMd3,
                        ]);

                        $model = PlayerModel::create([
                            'user_id' => $userId,
                            'name' => $finalModelName,
                            'base_model' => $detectedModelName,
                            'main_file' => $mainMd3,
                            'model_type' => 'complete',
                            'description' => $request->description,
                            'category' => 'weapon',
                            'author' => $metadata['author'] ?? null,
                            'author_email' => $metadata['author_email'] ?? null,
                            'file_path' => 'models/extracted/' . $slug . '/models/weapons2/' . $detectedModelName,
                            'zip_path' => $pk3PathForDownload,
                            'poly_count' => $metadata['poly_count'] ?? null,
                            'vert_count' => $metadata['vert_count'] ?? null,
                            'has_sounds' => false,
                            'has_ctf_skins' => false,
                            'available_skins' => json_encode($availableSkins),
                            'approval_status' => 'pending',
                            'is_nsfw' => $request->is_nsfw ?? false,
                        ]);

                        \Log::info('Weapon model created:', ['id' => $model->id]);
                        $createdModels[] = $model;
                    } else {
                        // PLAYER MODEL PROCESSING (existing logic)
                        // Parse metadata and available skins
                        $metadata = $this->parseModelMetadata($extractPath, $detectedModelName);

                        // Check if this model has MD3 files (complete custom model) or just skins
                        $hasMd3Files = $this->checkForMd3Files($extractPath, $detectedModelName);

                        // Determine base_model:
                        // - If has MD3 files: this IS a base model (use its own name)
                        // - If no MD3 files: this is a skin for an existing base model
                        $baseModel = $hasMd3Files ? $detectedModelName : $detectedModelName;

                        // Determine model type based on content
                        $modelType = $this->determineModelType($extractPath, $detectedModelName, $hasMd3Files, $metadata);

                        // Get available skins for this model
                        $availableSkins = $metadata['available_skins'] ?? ['default'];

                        // Determine base model file path for MD3 files
                        $baseModelFilePath = $this->determineBaseModelFilePath($extractPath, $detectedModelName, $hasMd3Files, $baseModel);

                        // Create a separate entry for each skin
                        foreach ($availableSkins as $skinName) {
                            // Determine display name:
                            // For multi-model packs: use "{ModelName} ({skin})"
                            // For single-model packs with one skin: use user-provided name
                            // For single-model packs with multiple skins: use "{user-provided name} ({skin})"
                            if ($isMultiModelPack) {
                                $finalModelName = ucfirst($detectedModelName) . ' (' . $skinName . ')';
                            } else {
                                // Single model pack
                                if (count($availableSkins) > 1) {
                                    $finalModelName = $modelName . ' (' . $skinName . ')';
                                } else {
                                    $finalModelName = $modelName;
                                }
                            }

                            // Create model record
                            $model = PlayerModel::create([
                                'user_id' => $userId,
                                'name' => $finalModelName,
                                'base_model' => $baseModel,
                                'base_model_file_path' => $baseModelFilePath,
                                'model_type' => $modelType,
                                'description' => $request->description,
                                'category' => $request->category,
                                'author' => $metadata['author'] ?? null,
                                'author_email' => $metadata['author_email'] ?? null,
                                'file_path' => 'models/extracted/' . $slug,
                                'zip_path' => $pk3PathForDownload,
                                'poly_count' => $metadata['poly_count'] ?? null,
                                'vert_count' => $metadata['vert_count'] ?? null,
                                'has_sounds' => $metadata['has_sounds'] ?? false,
                                'has_ctf_skins' => $metadata['has_ctf_skins'] ?? false,
                                'available_skins' => json_encode([$skinName]), // Store only this skin
                                'approval_status' => 'pending', // Requires admin approval
                                'is_nsfw' => $request->is_nsfw ?? false,
                            ]);

                            $createdModels[] = $model;
                        }
                    }
                }

                // Check if any models were created
                if (empty($createdModels)) {
                    $this->deleteDirectory($extractPath);
                    $errorMsg = $request->category === 'weapon'
                        ? 'No valid weapon models found. Make sure the PK3 contains MD3 files in models/weapons2/{name}/ directories.'
                        : 'No valid models found. Make sure the PK3 contains MD3 files in models/players/{name}/ directories.';
                    return back()->with('error', $errorMsg);
                }

                // Return to the first model's page
                $successMessage = count($createdModels) > 1
                    ? sprintf('Successfully uploaded %d model variations: %s. They will be visible once approved by an admin.',
                        count($createdModels),
                        implode(', ', array_map(fn($m) => $m->name, $createdModels)))
                    : sprintf('Model "%s" uploaded successfully! It will be visible once approved by an admin.', $createdModels[0]->name);

                return redirect()->route('models.show', $createdModels[0]->id)
                    ->with('success', $successMessage);
            }
        }

        return back()->with('error', 'Failed to extract model file. Make sure it\'s a valid PK3 or ZIP containing a PK3.');
    }

    /**
     * Show bulk upload form (admin only)
     */
    public function bulkUploadForm()
    {
        // Check if user is admin
        if (!Auth::user()->admin) {
            abort(403, 'Only admins can bulk upload models');
        }

        return Inertia::render('Models/BulkUpload');
    }

    /**
     * Handle bulk upload of multiple PK3 files (admin only)
     * Automatically extracts metadata from files without requiring manual input
     */
    public function bulkUpload(Request $request)
    {
        // Check if user is admin
        if (!Auth::user()->admin) {
            abort(403, 'Only admins can bulk upload models');
        }

        $request->validate([
            'model_files' => 'required|array|min:1',
            'model_files.*' => 'required|file|mimes:zip,pk3|max:51200', // 50MB max per file
            'category' => 'required|in:player,weapon,shadow',
        ]);

        $uploadedFiles = $request->file('model_files');
        $userId = Auth::id();
        $category = $request->category;
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($uploadedFiles as $uploadedFile) {
            try {
                $originalName = $uploadedFile->getClientOriginalName();
                $slug = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . time() . '-' . rand(1000, 9999);

                // Extracted files go to PUBLIC storage
                $extractPath = storage_path('app/public/models/extracted/' . $slug);

                // Original PK3 goes to PRIVATE storage
                $pk3StoragePath = storage_path('app/models/pk3s');
                if (!file_exists($pk3StoragePath)) {
                    mkdir($pk3StoragePath, 0755, true);
                }

                // Store original file temporarily (must be local for ZipArchive)
                $tempPath = $uploadedFile->storeAs('models/temp', $slug . '.' . $uploadedFile->getClientOriginalExtension(), 'local');
                $tempFullPath = storage_path('app/' . $tempPath);

                // Create extraction directory
                if (!file_exists($extractPath)) {
                    mkdir($extractPath, 0755, true);
                }

                $zip = new ZipArchive;
                $pk3Found = false;
                $pk3PathForDownload = null;

                if ($zip->open($tempFullPath) === TRUE) {
                    // Check if this is a ZIP containing PK3 files
                    $containsPK3 = false;
                    $pk3FileName = null;

                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        $basename = basename($filename);
                        $ext = pathinfo($basename, PATHINFO_EXTENSION);
                        $hasSlash = strpos($filename, '/');

                        if ($ext === 'pk3' && $hasSlash === false) {
                            $containsPK3 = true;
                            $pk3FileName = $filename;
                            break;
                        }
                    }

                    if ($containsPK3 && $pk3FileName) {
                        // Extract ZIP to find PK3
                        $tempExtract = storage_path('app/models/temp/' . $slug . '_extract');
                        if (!file_exists($tempExtract)) {
                            mkdir($tempExtract, 0755, true);
                        }

                        $zip->extractTo($tempExtract);
                        $zip->close();

                        $pk3File = $tempExtract . '/' . $pk3FileName;

                        if (file_exists($pk3File)) {
                            $pk3Found = true;
                            $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                            copy($pk3File, storage_path('app/' . $pk3PathForDownload));

                            // Extract the PK3 contents
                            $pk3Zip = new ZipArchive;
                            if ($pk3Zip->open($pk3File) === TRUE) {
                                $pk3Zip->extractTo($extractPath);
                                $pk3Zip->close();
                                $this->generateFileManifest($extractPath);
                            }
                        }

                        $this->deleteDirectory($tempExtract);
                    } else {
                        // Direct PK3 file
                        $hasProperStructure = false;

                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (stripos($filename, 'models/players/') === 0 || stripos($filename, 'sound/player/') === 0) {
                                $hasProperStructure = true;
                                break;
                            }
                        }

                        if ($hasProperStructure) {
                            $zip->extractTo($extractPath);
                            $zip->close();
                            $pk3Found = true;
                            $this->generateFileManifest($extractPath);

                            $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                            copy($tempFullPath, storage_path('app/' . $pk3PathForDownload));
                        } else {
                            $zip->close();
                        }
                    }

                    Storage::disk('local')->delete($tempPath);

                    if ($pk3Found) {
                        // Auto-detect ALL model names (PK3 might contain multiple models)
                        $detectedModelNames = $this->detectAllModelNames($extractPath);

                        if (empty($detectedModelNames)) {
                            $this->deleteDirectory($extractPath);
                            $results['failed'][] = [
                                'file' => $originalName,
                                'error' => 'Could not find any model folders'
                            ];
                            continue;
                        }

                        $createdModels = [];
                        $isMultiModelPack = count($detectedModelNames) > 1;

                        // Create a separate database entry for each model+skin combination
                        foreach ($detectedModelNames as $detectedModelName) {
                            // Parse metadata
                            $metadata = $this->parseModelMetadata($extractPath, $detectedModelName);
                            $hasMd3Files = $this->checkForMd3Files($extractPath, $detectedModelName);
                            $baseModel = $hasMd3Files ? $detectedModelName : $detectedModelName;
                            $modelType = $this->determineModelType($extractPath, $detectedModelName, $hasMd3Files, $metadata);

                            // Get available skins for this model
                            $availableSkins = $metadata['available_skins'] ?? ['default'];

                            // Determine base model file path for MD3 files
                            $baseModelFilePath = $this->determineBaseModelFilePath($extractPath, $detectedModelName, $hasMd3Files, $baseModel);

                            // Create a separate entry for each skin
                            foreach ($availableSkins as $skinName) {
                                // Determine display name:
                                // For multi-model packs: use "{ModelName} ({skin})"
                                // For single-model packs with one skin: use filename
                                // For single-model packs with multiple skins: use "{filename} ({skin})"
                                if ($isMultiModelPack) {
                                    $displayName = ucfirst($detectedModelName) . ' (' . $skinName . ')';
                                } else {
                                    // Single model pack
                                    if (count($availableSkins) > 1) {
                                        $displayName = pathinfo($originalName, PATHINFO_FILENAME) . ' (' . $skinName . ')';
                                    } else {
                                        $displayName = pathinfo($originalName, PATHINFO_FILENAME);
                                    }
                                }

                                // Create model record
                                $model = PlayerModel::create([
                                    'user_id' => $userId,
                                    'name' => $displayName,
                                    'base_model' => $baseModel,
                                    'base_model_file_path' => $baseModelFilePath,
                                    'model_type' => $modelType,
                                    'description' => $metadata['author'] ? "Created by {$metadata['author']}" : null,
                                    'category' => $category,
                                    'author' => $metadata['author'] ?? null,
                                    'author_email' => $metadata['author_email'] ?? null,
                                    'file_path' => 'models/extracted/' . $slug,
                                    'zip_path' => $pk3PathForDownload,
                                    'poly_count' => $metadata['poly_count'] ?? null,
                                    'vert_count' => $metadata['vert_count'] ?? null,
                                    'has_sounds' => $metadata['has_sounds'] ?? false,
                                    'has_ctf_skins' => $metadata['has_ctf_skins'] ?? false,
                                    'available_skins' => json_encode([$skinName]), // Store only this skin
                                    'approval_status' => 'approved', // Auto-approve for admin bulk uploads
                                ]);

                                $createdModels[] = [
                                    'id' => $model->id,
                                    'name' => $model->name,
                                ];
                            }
                        }

                        $results['success'][] = [
                            'file' => $originalName,
                            'model' => implode(', ', array_column($createdModels, 'name')),
                            'id' => $createdModels[0]['id'],
                            'created_count' => count($createdModels),
                        ];
                    } else {
                        $results['failed'][] = [
                            'file' => $originalName,
                            'error' => 'Not a valid PK3 file'
                        ];
                    }
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'file' => $uploadedFile->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
            }
        }

        return back()->with('bulkResults', $results);
    }

    /**
     * Find the models/players directory path, handling case variations (e.g. models/PLAYERS)
     */
    private function findPlayersPath($extractPath)
    {
        $playersPath = $extractPath . '/models/players';
        if (is_dir($playersPath)) {
            return $playersPath;
        }

        // Try case-insensitive: find 'models' dir first, then 'players' inside it
        $modelsDir = null;
        foreach (scandir($extractPath) as $entry) {
            if ($entry !== '.' && $entry !== '..' && strtolower($entry) === 'models' && is_dir($extractPath . '/' . $entry)) {
                $modelsDir = $extractPath . '/' . $entry;
                break;
            }
        }
        if (!$modelsDir) return null;

        foreach (scandir($modelsDir) as $entry) {
            if ($entry !== '.' && $entry !== '..' && strtolower($entry) === 'players' && is_dir($modelsDir . '/' . $entry)) {
                return $modelsDir . '/' . $entry;
            }
        }

        return null;
    }

    /**
     * Case-insensitive directory finder: resolve a path like 'models/weapons2' within $basePath.
     */
    private function findDirCaseInsensitive($basePath, array $segments)
    {
        $current = $basePath;
        foreach ($segments as $segment) {
            $exact = $current . '/' . $segment;
            if (is_dir($exact)) {
                $current = $exact;
                continue;
            }
            $found = false;
            if (!is_dir($current)) return null;
            foreach (scandir($current) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                if (strtolower($entry) === strtolower($segment) && is_dir($current . '/' . $entry)) {
                    $current = $current . '/' . $entry;
                    $found = true;
                    break;
                }
            }
            if (!$found) return null;
        }
        return $current;
    }

    /**
     * Find scripts directory (case-insensitive)
     */
    private function findScriptsPath($extractPath)
    {
        return $this->findDirCaseInsensitive($extractPath, ['scripts']);
    }

    /**
     * Find weapons path (case-insensitive): models/weapons2
     */
    private function findWeaponsPath($extractPath)
    {
        return $this->findDirCaseInsensitive($extractPath, ['models', 'weapons2']);
    }

    /**
     * Find sound/player path (case-insensitive)
     */
    private function findSoundPlayerPath($extractPath)
    {
        return $this->findDirCaseInsensitive($extractPath, ['sound', 'player']);
    }

    /**
     * Detect model name from extracted files
     * Looks for models/players/{name}/ directory
     */
    private function detectModelName($extractPath)
    {
        $playersPath = $this->findPlayersPath($extractPath);

        if (!$playersPath) {
            return null;
        }

        $dirs = array_diff(scandir($playersPath), ['.', '..']);

        foreach ($dirs as $dir) {
            if (is_dir($playersPath . '/' . $dir)) {
                return $dir; // Return the first model folder found
            }
        }

        return null;
    }

    /**
     * Detect ALL model names in a PK3 (for multi-model packs)
     * Returns array of model directory names
     */
    private function detectAllModelNames($extractPath)
    {
        $playersPath = $this->findPlayersPath($extractPath);

        if (!$playersPath) {
            return [];
        }

        $dirs = array_diff(scandir($playersPath), ['.', '..']);
        $modelNames = [];

        foreach ($dirs as $dir) {
            if (is_dir($playersPath . '/' . $dir)) {
                $modelNames[] = $dir;
            }
        }

        return $modelNames;
    }

    /**
     * Detect all weapon directory names in extracted PK3
     * Returns array of weapon directory names
     */
    private function detectAllWeaponNames($extractPath)
    {
        $weaponsPath = $this->findWeaponsPath($extractPath);
        if (!$weaponsPath) {
            return [];
        }

        $dirs = array_diff(scandir($weaponsPath), ['.', '..']);
        $weaponNames = [];

        foreach ($dirs as $dir) {
            if (is_dir($weaponsPath . '/' . $dir)) {
                $weaponNames[] = $dir;
            }
        }

        return $weaponNames;
    }

    /**
     * Build weapon model info array for tempUpload detection.
     * Works for both single-PK3 and multi-PK3 weapon uploads.
     */
    private function buildWeaponModelInfo($searchPath, $weaponName, $slug, $pk3SubDir, $pk3DisplayName, $pk3Path, $isMultiPk3)
    {
        $weaponsBase = $this->findWeaponsPath($searchPath);
        $weaponPath = $weaponsBase ? $weaponsBase . '/' . $weaponName : $searchPath . '/models/weapons2/' . $weaponName;
        $md3Files = glob($weaponPath . '/*.{md3,MD3}', GLOB_BRACE);
        $mainMd3 = null;

        if (!empty($md3Files)) {
            // Try exact match first (e.g., gauntlet.md3 for gauntlet/)
            foreach ($md3Files as $md3File) {
                $basename = strtolower(basename($md3File, '.md3'));
                if ($basename === strtolower($weaponName)) {
                    $mainMd3 = basename($md3File);
                    break;
                }
            }
            // Then try excluding known suffixes
            if (!$mainMd3) {
                foreach ($md3Files as $md3File) {
                    $basename = strtolower(basename($md3File, '.md3'));
                    if (!preg_match('/_(hand|flash|barrel|[0-9])$/i', $basename)) {
                        $mainMd3 = basename($md3File);
                        break;
                    }
                }
            }
            if (!$mainMd3) {
                $mainMd3 = basename($md3Files[0]);
            }
        }

        $skinFiles = glob($weaponPath . '/*.skin');
        $availableSkins = [];
        foreach ($skinFiles as $skinFile) {
            $skinBasename = basename($skinFile, '.skin');
            if (str_starts_with($skinBasename, $weaponName . '_')) {
                $skinName = str_replace($weaponName . '_', '', $skinBasename);
            } else {
                $skinName = $skinBasename;
            }
            if (!in_array($skinName, $availableSkins)) {
                $availableSkins[] = $skinName;
            }
        }
        if (empty($availableSkins)) {
            $availableSkins = ['default'];
        }

        // Build paths: for multi-PK3, include the __pk3_* subdir in the path
        if ($pk3SubDir) {
            $relFilePath = 'models/extracted/' . $slug . '/' . $pk3SubDir . '/models/weapons2/' . $weaponName;
            $viewerPath = $mainMd3
                ? '/storage/models/extracted/' . $slug . '/' . $pk3SubDir . '/models/weapons2/' . $weaponName . '/' . $mainMd3
                : null;
        } else {
            $relFilePath = 'models/extracted/' . $slug . '/models/weapons2/' . $weaponName;
            $viewerPath = $mainMd3
                ? '/storage/models/extracted/' . $slug . '/models/weapons2/' . $weaponName . '/' . $mainMd3
                : null;
        }

        // Fallback to baseq3 MD3 if this is a skin-only weapon pack
        $isWeaponSkinPack = !$mainMd3;
        if (!$viewerPath) {
            // Find the main MD3 from baseq3
            $baseq3WeaponPath = public_path('baseq3/models/weapons2/' . $weaponName);
            if (is_dir($baseq3WeaponPath)) {
                $baseq3Md3Files = glob($baseq3WeaponPath . '/*.{md3,MD3}', GLOB_BRACE);
                $baseq3MainMd3 = null;
                foreach ($baseq3Md3Files as $md3File) {
                    $basename = strtolower(basename($md3File, '.md3'));
                    if ($basename === strtolower($weaponName)) {
                        $baseq3MainMd3 = basename($md3File);
                        break;
                    }
                }
                if (!$baseq3MainMd3 && !empty($baseq3Md3Files)) {
                    foreach ($baseq3Md3Files as $md3File) {
                        $basename = strtolower(basename($md3File, '.md3'));
                        if (!preg_match('/_(hand|flash|barrel|[0-9])$/i', $basename)) {
                            $baseq3MainMd3 = basename($md3File);
                            break;
                        }
                    }
                }
                if ($baseq3MainMd3) {
                    $viewerPath = '/baseq3/models/weapons2/' . $weaponName . '/' . $baseq3MainMd3;
                    $mainMd3 = $baseq3MainMd3;
                }
            }
        }

        // Display name: use PK3 filename for multi-PK3, weapon dir name for multi-weapon single PK3
        if ($isMultiPk3 && $pk3DisplayName) {
            $finalName = str_replace(['-', '_'], ' ', $pk3DisplayName);
        } else {
            $finalName = ucfirst($weaponName);
        }

        return [
            'detected_name' => $weaponName,
            'display_name' => $finalName,
            'category' => 'weapon',
            'main_file' => $mainMd3,
            'available_skins' => $availableSkins,
            'file_path' => $relFilePath,
            'viewer_path' => $viewerPath,
            'pk3_path' => $pk3Path,
            'pk3_subdir' => $pk3SubDir,
            'is_skin_pack' => $isWeaponSkinPack,
        ];
    }

    /**
     * Build shadow model info for tempUpload detection.
     * Detects shadow textures in gfx/misc/ or gfx/damage/ and shader in scripts/.
     */
    private function buildShadowModelInfo($searchPath, $slug, $pk3SubDir, $pk3DisplayName, $pk3Path)
    {
        $shadowFiles = $this->detectShadowFiles($searchPath);
        if (empty($shadowFiles['textures']) && empty($shadowFiles['shader'])) {
            return null;
        }

        // Build relative paths
        if ($pk3SubDir) {
            $relFilePath = 'models/extracted/' . $slug . '/' . $pk3SubDir;
            $viewerBasePath = '/storage/models/extracted/' . $slug . '/' . $pk3SubDir;
        } else {
            $relFilePath = 'models/extracted/' . $slug;
            $viewerBasePath = '/storage/models/extracted/' . $slug;
        }

        // Display name
        if ($pk3DisplayName) {
            $finalName = str_replace(['-', '_'], ' ', $pk3DisplayName);
        } else {
            $finalName = 'Shadow';
        }

        return [
            'detected_name' => 'shadow',
            'display_name' => $finalName,
            'category' => 'shadow',
            'main_file' => null,
            'available_skins' => ['default'],
            'file_path' => $relFilePath,
            'viewer_path' => $viewerBasePath, // base path for shadow viewer to find textures
            'shadow_textures' => $shadowFiles['textures'],
            'shadow_shader' => $shadowFiles['shader'],
            'pk3_path' => $pk3Path,
            'pk3_subdir' => $pk3SubDir,
        ];
    }

    /**
     * Detect shadow files in an extracted directory.
     * Returns ['textures' => [...], 'shader' => string|null]
     */
    private function detectShadowFiles($extractPath)
    {
        $textures = [];
        $shaderContent = null;

        // Look for shadow textures in gfx/misc/ and gfx/damage/
        $textureDirs = ['gfx/misc', 'gfx/damage'];
        foreach ($textureDirs as $dir) {
            $fullDir = $extractPath . '/' . $dir;
            if (!is_dir($fullDir)) continue;

            foreach (scandir($fullDir) as $file) {
                if ($file === '.' || $file === '..') continue;
                $lower = strtolower($file);
                if (str_starts_with($lower, 'shadow') &&
                    preg_match('/\.(tga|jpg|jpeg|png|bmp)$/i', $lower)) {
                    $textures[] = $dir . '/' . $file;
                }
            }
        }

        // Look for shadow shader
        $scriptsDirs = ['scripts'];
        foreach ($scriptsDirs as $dir) {
            $fullDir = $extractPath . '/' . $dir;
            if (!is_dir($fullDir)) continue;

            foreach (scandir($fullDir) as $file) {
                if ($file === '.' || $file === '..') continue;
                $lower = strtolower($file);
                if (str_starts_with($lower, 'shadow') && str_ends_with($lower, '.shader')) {
                    $shaderContent = file_get_contents($fullDir . '/' . $file);
                    break 2;
                }
            }
        }

        return ['textures' => $textures, 'shader' => $shaderContent];
    }

    /**
     * Check if model directory contains MD3 files (complete model)
     * Returns true if has head.md3, upper.md3, lower.md3
     * Returns false if only has skins/textures (skin-only upload)
     */
    private function checkForMd3Files($extractPath, $modelName)
    {
        $playersPath = $this->findPlayersPath($extractPath);
        if (!$playersPath) return false;

        $modelPath = $playersPath . '/' . $modelName;

        if (!is_dir($modelPath)) {
            return false;
        }

        // Check for the three required MD3 files (case-insensitive)
        $files = scandir($modelPath);
        $lowerFiles = array_map('strtolower', $files);
        $hasHead = in_array('head.md3', $lowerFiles);
        $hasUpper = in_array('upper.md3', $lowerFiles);
        $hasLower = in_array('lower.md3', $lowerFiles);

        // Model is complete if it has all three MD3 files
        return $hasHead && $hasUpper && $hasLower;
    }

    /**
     * Determine the type of model based on its contents
     * Types:
     * - complete: Has MD3 files (full custom model)
     * - skin: Only has skin files (.skin, .tga, .jpg) - no MD3
     * - sound: Only has sound files - no MD3, no skins
     * - mixed: Has combination (skins + sounds, or any other mix without MD3)
     */
    private function determineModelType($extractPath, $modelName, $hasMd3Files, $metadata)
    {
        // If has MD3 files, it's a complete model
        if ($hasMd3Files) {
            return 'complete';
        }

        // Otherwise check what content it has
        $hasSkins = false;
        $hasSounds = $metadata['has_sounds'] ?? false;
        $hasShaders = false;

        // Check for skin files
        $playersPath = $this->findPlayersPath($extractPath);
        $modelPath = $playersPath ? $playersPath . '/' . $modelName : $extractPath . '/models/players/' . $modelName;
        if (is_dir($modelPath)) {
            $skinFiles = glob($modelPath . '/*.skin');
            $textureFiles = array_merge(
                glob($modelPath . '/*.tga'),
                glob($modelPath . '/*.jpg'),
                glob($modelPath . '/*.jpeg'),
                glob($modelPath . '/*.png')
            );
            $hasSkins = !empty($skinFiles) || !empty($textureFiles);
        }

        // Check for shader files
        $scriptsPath = $this->findScriptsPath($extractPath);
        if ($scriptsPath && is_dir($scriptsPath)) {
            $shaderFiles = glob($scriptsPath . '/*.shader');
            $hasShaders = !empty($shaderFiles);
        }

        // Determine type based on content
        if ($hasSkins && !$hasSounds && !$hasShaders) {
            return 'skin';
        } elseif ($hasSounds && !$hasSkins && !$hasShaders) {
            return 'sound';
        } else {
            // Has multiple types of content (skins + sounds, skins + shaders, etc.)
            return 'mixed';
        }
    }

    /**
     * Display the specified model
     */
    public function show(Request $request, $id)
    {
        $totalStart = microtime(true);
        $timings = [];

        $start = microtime(true);
        $model = PlayerModel::with('user')->findOrFail($id);
        $timings['model_query'] = round((microtime(true) - $start) * 1000, 2);

        // Only show approved and non-hidden models unless user is the owner or admin
        $isOwnerOrAdmin = Auth::check() && (Auth::id() === $model->user_id || Auth::user()->is_admin);
        if (($model->approval_status !== 'approved' || $model->hidden) && !$isOwnerOrAdmin) {
            abort(404);
        }

        // NSFW gate: require login for NSFW models
        if ($model->is_nsfw && !$isOwnerOrAdmin && !Auth::check()) {
            return redirect()->route('login');
        }

        // Resolve base model data for texture/geometry fallback
        // base_model_file_path: works for any model type (skin packs AND complete models with texture dependencies)
        // Fallback query: only for skin/mixed packs without cached path
        $start = microtime(true);
        $baseModelData = null;
        if ($model->base_model_file_path) {
            // Use stored path (optimized) - works for skin packs and complete models with cross-PK3 texture deps
            $baseModelData = [
                'name' => $model->base_model,
                'file_path' => $model->base_model_file_path,
            ];
            // For complete models with cross-PK3 texture deps, fetch base model ID and download info
            if ($model->model_type === 'complete') {
                $depModel = PlayerModel::where('file_path', $model->base_model_file_path)
                    ->where('id', '!=', $model->id)
                    ->first(['id', 'name', 'zip_path']);
                if ($depModel) {
                    $baseModelData['id'] = $depModel->id;
                    $baseModelData['display_name'] = $depModel->name;
                    $baseModelData['zip_path'] = $depModel->zip_path;
                    $baseModelData['is_texture_dependency'] = true;
                }
            }
            $timings['base_model_resolution'] = round((microtime(true) - $start) * 1000, 2) . ' (cached)';
        } elseif ($model->model_type !== 'complete' && $model->base_model) {
            // Fallback: query database (old skin/mixed packs without base_model_file_path)
            $existingModel = PlayerModel::whereRaw('LOWER(base_model) = ?', [strtolower($model->base_model)])
                ->where('model_type', 'complete')
                ->first(['name', 'file_path', 'base_model']);

            if ($existingModel) {
                $baseModelData = [
                    'name' => $existingModel->name,
                    'file_path' => $existingModel->file_path,
                ];
            }
            $timings['base_model_resolution'] = round((microtime(true) - $start) * 1000, 2) . ' (db query)';
        } else {
            $timings['base_model_resolution'] = '0 (not needed)';
        }

        $timings['total'] = round((microtime(true) - $totalStart) * 1000, 2);

        // Resolve bundled models — only from OTHER PK3s (same PK3 = siblings, not dependencies)
        $bundledModels = [];
        if ($model->bundle_uuid) {
            $bundledModels = PlayerModel::where('bundle_uuid', $model->bundle_uuid)
                ->where('zip_path', '!=', $model->zip_path)
                ->select(['id', 'name', 'base_model', 'zip_path', 'thumbnail_path'])
                ->get()
                ->toArray();
        }

        // For thumbnail generation, render without layout
        if ($request->has('thumbnail')) {
            return Inertia::render('Models/ShowThumbnail', [
                'model' => $model,
                'baseModelData' => $baseModelData,
            ]);
        }

        // For admins or owners of pending models, include sibling models for delete confirmation
        $siblingModels = [];
        $canDelete = $isOwnerOrAdmin && (Auth::user()->admin || $model->approval_status === 'pending');
        if ($canDelete && $model->zip_path) {
            $siblingModels = PlayerModel::where('zip_path', $model->zip_path)
                ->where('id', '!=', $model->id)
                ->with('user:id,name')
                ->select(['id', 'name', 'user_id', 'created_at'])
                ->get()
                ->toArray();
        }

        // For shadow models, detect shadow files for the viewer
        $shadowData = null;
        if ($model->category === 'shadow') {
            $shadowExtractPath = storage_path('app/public/' . $model->file_path);
            $shadowFiles = $this->detectShadowFiles($shadowExtractPath);
            $shadowData = [
                'textures' => $shadowFiles['textures'],
                'shader' => $shadowFiles['shader'],
                'viewer_path' => '/storage/' . $model->file_path,
            ];
        }

        // For weapon models, detect skin packs (MD3 from baseq3, textures from PK3)
        $weaponViewerData = null;
        if ($model->category === 'weapon' && $model->main_file && !str_starts_with($model->file_path, 'baseq3/')) {
            $extractPath = storage_path('app/public/' . $model->file_path);
            $isSkinPack = !file_exists($extractPath . '/' . $model->main_file);
            if ($isSkinPack) {
                $weaponName = $model->base_model ?? strtolower($model->name);
                $weaponViewerData = [
                    'is_skin_pack' => true,
                    'baseq3_model_path' => '/baseq3/models/weapons2/' . strtolower($weaponName) . '/' . $model->main_file,
                    'skin_pack_base_path' => '/storage/' . $model->file_path,
                ];
            }
        }

        $model->incrementViews();

        return Inertia::render('Models/Show', [
            'model' => $model,
            'baseModelData' => $baseModelData,
            'bundledModels' => $bundledModels,
            'siblingModels' => $siblingModels,
            'shadowData' => $shadowData,
            'weaponViewerData' => $weaponViewerData,
            'load_times' => $timings,
        ]);
    }

    /**
     * Approve a model (admin only)
     */
    public function approveModel($id)
    {
        if (!Auth::check() || !Auth::user()->admin) {
            abort(403);
        }

        $model = PlayerModel::findOrFail($id);
        $model->approval_status = 'approved';
        $model->save();

        return response()->json(['success' => true, 'message' => "Model \"{$model->name}\" approved."]);
    }

    /**
     * Delete a model and all sibling models sharing the same PK3.
     * Admin can delete any model. Owner can only delete pending (not yet approved) models.
     */
    public function destroyModel($id)
    {
        if (!Auth::check()) {
            abort(403);
        }

        $model = PlayerModel::findOrFail($id);
        $isAdmin = Auth::user()->admin;
        $isOwner = Auth::id() === $model->user_id;

        if (!$isAdmin && !$isOwner) {
            abort(403);
        }

        // Owners can only delete pending models
        if ($isOwner && !$isAdmin && $model->approval_status !== 'pending') {
            abort(403, 'You can only delete models that are pending approval.');
        }

        // Find all models sharing the same PK3
        $modelsToDelete = $model->zip_path
            ? PlayerModel::where('zip_path', $model->zip_path)->get()
            : collect([$model]);

        $deletedNames = [];

        foreach ($modelsToDelete as $m) {
            // Delete GIF/thumbnail files
            foreach (['rotate_gif', 'idle_gif', 'gesture_gif', 'thumbnail', 'head_icon'] as $field) {
                if ($m->$field && Storage::disk('public')->exists($m->$field)) {
                    Storage::disk('public')->delete($m->$field);
                }
            }

            $deletedNames[] = $m->name;
            $m->delete();
        }

        // Delete extracted files directory (shared by all models from same PK3)
        if ($model->file_path) {
            // file_path is like "models/extracted/testing-1773175654" - delete the root extracted dir
            $parts = explode('/', $model->file_path);
            // Find the "models/extracted/<hash>" root
            $extractRoot = null;
            for ($i = 0; $i < count($parts); $i++) {
                if ($parts[$i] === 'extracted' && $i > 0 && isset($parts[$i + 1])) {
                    $extractRoot = implode('/', array_slice($parts, 0, $i + 2));
                    break;
                }
            }
            if ($extractRoot) {
                $extractedFullPath = storage_path('app/public/' . $extractRoot);
                if (is_dir($extractedFullPath)) {
                    $this->deleteDirectory($extractedFullPath);
                }
            }
        }

        // Delete PK3 file
        if ($model->zip_path) {
            $pk3Path = storage_path('app/' . $model->zip_path);
            if (file_exists($pk3Path)) {
                unlink($pk3Path);
            }
        }

        $count = count($deletedNames);
        $message = $count > 1
            ? "Deleted {$count} models: " . implode(', ', $deletedNames)
            : "Model \"{$deletedNames[0]}\" deleted.";

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Download the model
     */
    public function download($id)
    {
        $model = PlayerModel::findOrFail($id);

        // Allow download if:
        // 1. Model is approved (public access)
        // 2. User owns the model (can download their own uploads regardless of approval status)
        if ($model->approval_status !== 'approved' && $model->user_id !== Auth::id()) {
            abort(403);
        }

        $model->incrementDownloads();

        // PK3 files are stored in private storage (storage/app/models/pk3s/)
        $filePath = storage_path('app/' . $model->zip_path);

        if (!file_exists($filePath)) {
            abort(404, 'Model file not found');
        }

        // Shadow and weapon models need zzzzz- prefix to override baseq3 defaults
        $prefix = in_array($model->category, ['shadow', 'weapon']) ? 'zzzzz-' : '';
        $filename = $prefix . str_replace(' ', '_', $model->name) . '.pk3';
        return response()->download($filePath, $filename);
    }

    /**
     * Download extras ZIP (source files, readmes, etc. from original author package)
     */
    public function downloadExtras(PlayerModel $model)
    {
        if ($model->approval_status !== 'approved' && $model->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$model->extras_zip_path) {
            abort(404, 'No extras available for this model');
        }

        $filePath = storage_path('app/' . $model->extras_zip_path);

        if (!file_exists($filePath)) {
            abort(404, 'Extras file not found');
        }

        $filename = str_replace(' ', '_', $model->name) . '-extras.zip';
        return response()->download($filePath, $filename);
    }

    /**
     * Parse model metadata from extracted files
     */
    private function parseModelMetadata($extractPath, $modelName)
    {
        $metadata = [
            'author' => null,
            'author_email' => null,
            'poly_count' => null,
            'vert_count' => null,
            'has_sounds' => false,
            'has_ctf_skins' => false,
            'available_skins' => ['default'],
        ];

        // Look for readme/txt files
        $files = glob($extractPath . '/*.{txt,TXT}', GLOB_BRACE);
        if (!empty($files)) {
            $content = file_get_contents($files[0]);

            // Parse author
            if (preg_match('/Author\s*:?\s*(.+)/i', $content, $matches)) {
                $metadata['author'] = trim($matches[1]);
            }

            // Parse email
            if (preg_match('/Email.*?:?\s*(.+@.+\..+)/i', $content, $matches)) {
                $metadata['author_email'] = trim($matches[1]);
            }

            // Parse poly count
            if (preg_match('/Poly Count\s*:?\s*(\d+)/i', $content, $matches)) {
                $metadata['poly_count'] = (int)$matches[1];
            }

            // Parse vert count
            if (preg_match('/Vert Count\s*:?\s*(\d+)/i', $content, $matches)) {
                $metadata['vert_count'] = (int)$matches[1];
            }

            // Check for sounds
            if (preg_match('/New Sounds\s*:?\s*yes/i', $content)) {
                $metadata['has_sounds'] = true;
            }

            // Check for CTF skins
            if (preg_match('/CTF Skins\s*:?\s*yes/i', $content)) {
                $metadata['has_ctf_skins'] = true;
            }
        }

        // Check for sound files (case-insensitive)
        $soundPath = $this->findDirCaseInsensitive($extractPath, ['sound']);
        if ($soundPath) {
            $metadata['has_sounds'] = true;
        }

        // Parse available skins from skin files
        $playersPath = $this->findPlayersPath($extractPath);
        $modelPath = $playersPath ? $playersPath . '/' . $modelName : $extractPath . '/models/players/' . $modelName;
        if (is_dir($modelPath)) {
            $skinFiles = glob($modelPath . '/*_*.skin');
            $skins = [];

            foreach ($skinFiles as $skinFile) {
                $filename = basename($skinFile, '.skin');
                // Extract skin name from pattern: head_default.skin, lower_red.skin, upper_blue.skin
                if (preg_match('/_(.+)$/', $filename, $matches)) {
                    $skinName = $matches[1];
                    if (!in_array($skinName, $skins)) {
                        $skins[] = $skinName;
                    }
                }
            }

            if (!empty($skins)) {
                // Sort skins: default first, then alphabetically
                usort($skins, function($a, $b) {
                    if ($a === 'default') return -1;
                    if ($b === 'default') return 1;
                    return strcmp($a, $b);
                });

                $metadata['available_skins'] = $skins;

                // Check if has CTF skins (red and blue)
                if (in_array('red', $skins) && in_array('blue', $skins)) {
                    $metadata['has_ctf_skins'] = true;
                }
            }
        }

        return $metadata;
    }

    /**
     * Parse weapon metadata from README or other files
     */
    private function parseWeaponMetadata($extractPath, $weaponName)
    {
        $metadata = [
            'author' => null,
            'author_email' => null,
            'poly_count' => null,
            'vert_count' => null,
        ];

        // Look for readme/txt files in the weapon directory
        $weaponsBase = $this->findWeaponsPath($extractPath);
        $weaponPath = ($weaponsBase ?: $extractPath . '/models/weapons2') . '/' . $weaponName;
        $files = glob($weaponPath . '/*.{txt,TXT}', GLOB_BRACE);

        // Also check root of PK3
        if (empty($files)) {
            $files = glob($extractPath . '/*.{txt,TXT}', GLOB_BRACE);
        }

        if (!empty($files)) {
            $content = file_get_contents($files[0]);

            // Parse author
            if (preg_match('/Author\s*:?\s*(.+)/i', $content, $matches)) {
                $metadata['author'] = trim($matches[1]);
            }

            // Parse email
            if (preg_match('/Email.*?:?\s*(.+@.+\..+)/i', $content, $matches)) {
                $metadata['author_email'] = trim($matches[1]);
            }

            // Parse poly count
            if (preg_match('/Poly Count\s*:?\s*(\d+)/i', $content, $matches)) {
                $metadata['poly_count'] = (int)$matches[1];
            }

            // Parse vert count
            if (preg_match('/Vert Count\s*:?\s*(\d+)/i', $content, $matches)) {
                $metadata['vert_count'] = (int)$matches[1];
            }
        }

        return $metadata;
    }

    /**
     * Save browser-generated GIF thumbnail for a model
     */
    public function saveThumbnail($id, Request $request)
    {
        $model = PlayerModel::findOrFail($id);

        // Check permission - only owner or admin
        if (!Auth::check() || (Auth::id() !== $model->user_id && !Auth::user()->admin)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // Validate the uploaded files - at least one GIF variant required
            $request->validate([
                'gif' => 'nullable|file|mimes:gif|max:10240', // 10MB max (legacy rotate)
                'rotate_gif' => 'nullable|file|mimes:gif|max:10240',
                'idle_gif' => 'nullable|file|mimes:gif|max:10240',
                'gesture_gif' => 'nullable|file|mimes:gif|max:10240',
                'head_icon' => 'nullable|file|mimes:png,jpg,jpeg|max:1024', // 1MB max
                'thumbnail' => 'nullable|file|mimes:png,jpg,jpeg|max:2048', // 2MB max still image
            ]);

            // Create thumbnails directory if it doesn't exist
            $thumbnailsDir = storage_path('app/public/thumbnails');
            if (!file_exists($thumbnailsDir)) {
                mkdir($thumbnailsDir, 0755, true);
            }

            $updateData = [];

            // Save GIF variants
            $gifTypes = [
                'rotate_gif' => "model_{$id}_rotate.gif",
                'idle_gif' => "model_{$id}_idle.gif",
                'gesture_gif' => "model_{$id}_gesture.gif",
            ];

            foreach ($gifTypes as $field => $filename) {
                if ($request->hasFile($field)) {
                    $request->file($field)->storeAs('public/thumbnails', $filename, 'local');
                    $updateData[$field] = "thumbnails/{$filename}";
                }
            }

            // Legacy support: 'gif' field saves as both thumbnail and rotate_gif
            if ($request->hasFile('gif')) {
                $gifFile = $request->file('gif');
                $legacyFilename = "model_{$id}.gif";
                $gifFile->storeAs('public/thumbnails', $legacyFilename, 'local');
                $updateData['thumbnail'] = "thumbnails/{$legacyFilename}";
                if (!isset($updateData['rotate_gif'])) {
                    $updateData['rotate_gif'] = "thumbnails/{$legacyFilename}";
                }
            }

            // Save still thumbnail if provided
            if ($request->hasFile('thumbnail')) {
                $thumbFile = $request->file('thumbnail');
                $thumbFilename = "model_{$id}_still.png";
                $thumbFile->storeAs('public/thumbnails', $thumbFilename, 'local');
                $updateData['thumbnail'] = "thumbnails/{$thumbFilename}";
            }

            // Save head icon if provided
            if ($request->hasFile('head_icon')) {
                $headIconFile = $request->file('head_icon');
                $headIconFilename = "model_{$id}_head.png";
                $headIconFile->storeAs('public/thumbnails', $headIconFilename, 'local');
                $updateData['head_icon'] = "thumbnails/{$headIconFilename}";
            }

            if (empty($updateData)) {
                return response()->json(['success' => false, 'message' => 'No files provided'], 422);
            }

            // Update model with all paths
            $model->update($updateData);

            \Log::info("Thumbnails saved for model {$id}: " . implode(', ', array_keys($updateData)));

            return response()->json([
                'success' => true,
                'updated' => array_keys($updateData),
                'message' => 'Thumbnails saved successfully!'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error("Validation error saving thumbnail for model {$id}: " . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Invalid file: ' . implode(', ', $e->errors()['gif'] ?? ['Unknown error'])
            ], 422);
        } catch (\Exception $e) {
            \Log::error("Error saving thumbnail for model {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save thumbnail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save browser-generated head icon (64x64 PNG) for a model
     */
    public function saveHeadIcon($id, Request $request)
    {
        $model = PlayerModel::findOrFail($id);

        // Check permission - only owner or admin
        if (!Auth::check() || (Auth::id() !== $model->user_id && !Auth::user()->admin)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // Validate the uploaded file
            $request->validate([
                'head_icon' => 'required|file|mimes:png,jpg,jpeg|max:1024', // 1MB max
            ]);

            // Create thumbnails directory if it doesn't exist
            $thumbnailsDir = storage_path('app/public/thumbnails');
            if (!file_exists($thumbnailsDir)) {
                mkdir($thumbnailsDir, 0755, true);
            }

            // Save head icon
            $headIconFile = $request->file('head_icon');
            $headIconFilename = "model_{$id}_head.png";
            $headIconFile->storeAs('public/thumbnails', $headIconFilename, 'local');

            // Update model with head icon path
            $model->update(['head_icon' => "thumbnails/{$headIconFilename}"]);

            \Log::info("Head icon saved for model {$id}");

            return response()->json([
                'success' => true,
                'head_icon' => "thumbnails/{$headIconFilename}",
                'message' => 'Head icon generated successfully!'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error("Validation error saving head icon for model {$id}: " . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Invalid file: ' . implode(', ', $e->errors()['head_icon'] ?? ['Unknown error'])
            ], 422);
        } catch (\Exception $e) {
            \Log::error("Error saving head icon for model {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save head icon: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine the base model file path for MD3 files
     * This resolves where the actual MD3 geometry files are located
     *
     * For complete models: uses own file_path
     * For skin/mixed packs: tries to find base Q3 model or existing uploaded base model
     */
    private function determineBaseModelFilePath($extractPath, $modelName, $hasMd3Files, $baseModel)
    {
        // If has MD3 files, it's complete - use its own path
        if ($hasMd3Files) {
            return null; // Will use file_path
        }

        // For skin/mixed packs, try to find the base model
        // Priority 1: Check if it's a base Q3 model (pak0-pak8.pk3)
        $baseQ3Models = [
            'sarge', 'grunt', 'major', 'visor', 'slash', 'biker', 'tankjr',
            'orbb', 'crash', 'razor', 'doom', 'klesk', 'anarki', 'xaero',
            'mynx', 'hunter', 'bones', 'sorlag', 'lucy', 'keel', 'uriel'
        ];

        if (in_array(strtolower($baseModel), $baseQ3Models)) {
            // It's a base Q3 model
            return 'baseq3/models/players/' . strtolower($baseModel);
        }

        // Priority 2: Try to find an existing user-uploaded complete model with this base_model name
        // This could be someone's custom model that others are making skins for
        $existingBaseModel = PlayerModel::where('base_model', $baseModel)
            ->where('model_type', 'complete')
            ->orderBy('created_at', 'asc') // Get the oldest (original) model
            ->first(['file_path']);

        if ($existingBaseModel) {
            return $existingBaseModel->file_path;
        }

        // Priority 3: Try matching by name (for backwards compatibility)
        $existingBaseModel = PlayerModel::where('name', 'LIKE', $baseModel . '%')
            ->where('model_type', 'complete')
            ->orderBy('created_at', 'asc')
            ->first(['file_path']);

        if ($existingBaseModel) {
            return $existingBaseModel->file_path;
        }

        // Fallback: return null (will need to be resolved at runtime)
        // This happens when someone uploads a skin for a model that hasn't been uploaded yet
        return null;
    }

    /**
     * Generate a file manifest (JSON) for extracted PK3 contents.
     * Lists all files with their exact paths for case-insensitive frontend lookups.
     */
    private function generateFileManifest($extractPath)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                // Get path relative to extractPath
                $relativePath = str_replace($extractPath . '/', '', $file->getPathname());
                $files[] = $relativePath;
            }
        }

        file_put_contents($extractPath . '/manifest.json', json_encode($files));
    }

    /**
     * Recursively delete a directory
     */
    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Get list of shader files for a weapon model
     */
    public function getShaders($id)
    {
        $model = PlayerModel::findOrFail($id);

        // For weapons, file_path is like: models/extracted/weapon1test-1760789758/models/weapons2/plasma
        // We need to get to: models/extracted/weapon1test-1760789758/scripts
        // So we need to extract the "models/extracted/XXX" part (first 3 path segments)
        $pathParts = explode('/', $model->file_path);
        $extractPath = storage_path('app/public/' . implode('/', array_slice($pathParts, 0, 3)));

        // Case-insensitive scripts directory lookup (PK3 files may have SCRIPTS, Scripts, scripts, etc.)
        $scriptsPath = null;
        if (is_dir($extractPath)) {
            foreach (scandir($extractPath) as $dir) {
                if (strcasecmp($dir, 'scripts') === 0) {
                    $scriptsPath = $extractPath . '/' . $dir;
                    break;
                }
            }
        }

        $shaderFiles = [];
        if ($scriptsPath && is_dir($scriptsPath)) {
            $allShaders = array_merge(
                glob($scriptsPath . '/*.shader') ?: [],
                glob($scriptsPath . '/*.shaderx') ?: []
            );
            foreach ($allShaders as $shaderFile) {
                $shaderFiles[] = basename($shaderFile);
            }
        }

        return response()->json(['shaders' => $shaderFiles]);
    }

    public function batchGenerateGifs()
    {
        if (!Auth::check() || !Auth::user()->admin) {
            abort(403);
        }

        $models = PlayerModel::select('id', 'name', 'category', 'model_type', 'file_path', 'base_model', 'base_model_file_path', 'thumbnail', 'head_icon', 'main_file', 'available_skins', 'idle_gif', 'rotate_gif', 'gesture_gif')
            ->approved()
            ->where('hidden', false)
            ->orderBy('id', 'desc')
            ->get();

        // Resolve base_model_file_path for skin/mixed models missing it (same fallback as show())
        $needsResolution = $models->filter(fn ($m) => !$m->base_model_file_path && $m->model_type !== 'complete' && $m->base_model);
        if ($needsResolution->isNotEmpty()) {
            $baseNames = $needsResolution->pluck('base_model')->map(fn ($b) => strtolower($b))->unique();
            $baseModels = PlayerModel::where('model_type', 'complete')
                ->whereRaw('LOWER(base_model) IN (' . $baseNames->map(fn () => '?')->implode(',') . ')', $baseNames->values()->all())
                ->get(['base_model', 'file_path'])
                ->keyBy(fn ($m) => strtolower($m->base_model));

            foreach ($needsResolution as $model) {
                $base = $baseModels->get(strtolower($model->base_model));
                if ($base) {
                    $model->base_model_file_path = $base->file_path;
                }
            }
        }

        return Inertia::render('Models/BatchGenerateGifs', [
            'models' => $models,
        ]);
    }

    public function confirmNsfw()
    {
        $user = Auth::user();
        $user->nsfw_confirmed = true;
        $user->save();

        return back();
    }

    public function flagNsfw($id)
    {
        if (!Auth::check()) {
            abort(403);
        }

        $model = PlayerModel::findOrFail($id);

        if ($model->is_nsfw) {
            return response()->json(['success' => false, 'message' => 'Already flagged as NSFW.']);
        }

        $model->is_nsfw = true;
        $model->save();

        return response()->json(['success' => true, 'message' => "Model \"{$model->name}\" flagged as NSFW."]);
    }

    public function unflagNsfw($id)
    {
        if (!Auth::check()) {
            abort(403);
        }

        $model = PlayerModel::findOrFail($id);
        $model->is_nsfw = false;
        $model->save();

        return response()->json(['success' => true, 'message' => "Model \"{$model->name}\" NSFW flag removed."]);
    }

    /**
     * Generate still PNG thumbnail from idle GIF (middle frame extraction)
     */
    public function generateStillThumbnail(Request $request, $id)
    {
        if (!Auth::check() || !Auth::user()->admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $model = PlayerModel::findOrFail($id);

        if (!$model->idle_gif) {
            return response()->json(['success' => false, 'message' => 'No idle GIF available'], 422);
        }

        $gifPath = storage_path('app/public/' . $model->idle_gif);
        if (!file_exists($gifPath)) {
            return response()->json(['success' => false, 'message' => 'Idle GIF file not found on disk'], 404);
        }

        try {
            $imagick = new \Imagick($gifPath);
            $frameCount = $imagick->getNumberImages();
            $middleIndex = (int) floor($frameCount / 2);

            $coalesced = $imagick->coalesceImages();
            for ($i = 0; $i < $middleIndex; $i++) {
                $coalesced->nextImage();
            }

            $coalesced->setImageFormat('png');
            $pngData = $coalesced->getImageBlob();
            $coalesced->clear();
            $imagick->clear();

            $thumbnailsDir = storage_path('app/public/thumbnails');
            if (!file_exists($thumbnailsDir)) {
                mkdir($thumbnailsDir, 0755, true);
            }

            $filename = "model_{$id}.png";
            file_put_contents($thumbnailsDir . '/' . $filename, $pngData);
            $model->update(['thumbnail' => "thumbnails/{$filename}"]);

            return response()->json([
                'success' => true,
                'thumbnail' => "thumbnails/{$filename}",
                'frames' => $frameCount,
                'extracted_frame' => $middleIndex,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Batch generate still thumbnails from idle GIFs
     */
    public function batchGenerateStillThumbnails(Request $request)
    {
        if (!Auth::check() || !Auth::user()->admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No IDs provided'], 422);
        }

        // Process max 50 at a time to avoid timeout
        $ids = array_slice($ids, 0, 50);
        $models = PlayerModel::whereIn('id', $ids)->whereNotNull('idle_gif')->get();

        $thumbnailsDir = storage_path('app/public/thumbnails');
        if (!file_exists($thumbnailsDir)) {
            mkdir($thumbnailsDir, 0755, true);
        }

        $results = [];
        foreach ($models as $model) {
            $gifPath = storage_path('app/public/' . $model->idle_gif);
            if (!file_exists($gifPath)) {
                $results[] = ['id' => $model->id, 'status' => 'error', 'message' => 'GIF not found'];
                continue;
            }

            try {
                $imagick = new \Imagick($gifPath);
                $frameCount = $imagick->getNumberImages();
                $middleIndex = (int) floor($frameCount / 2);

                $coalesced = $imagick->coalesceImages();
                for ($i = 0; $i < $middleIndex; $i++) {
                    $coalesced->nextImage();
                }

                $coalesced->setImageFormat('png');
                $pngData = $coalesced->getImageBlob();
                $coalesced->clear();
                $imagick->clear();

                $filename = "model_{$model->id}.png";
                file_put_contents($thumbnailsDir . '/' . $filename, $pngData);
                $model->update(['thumbnail' => "thumbnails/{$filename}"]);

                $results[] = ['id' => $model->id, 'status' => 'ok'];
            } catch (\Exception $e) {
                $results[] = ['id' => $model->id, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        $okCount = count(array_filter($results, fn($r) => $r['status'] === 'ok'));
        return response()->json([
            'success' => true,
            'processed' => count($results),
            'ok' => $okCount,
            'failed' => count($results) - $okCount,
            'results' => $results,
        ]);
    }

    public function scrapeWsMetadata(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !$user->admin) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate(['url' => 'required|url']);
        $url = $request->input('url');

        $model = PlayerModel::findOrFail($id);

        try {
            $response = Http::withoutVerifying()->timeout(10)->get($url);
            if (!$response->ok()) {
                return response()->json(['error' => 'Failed to fetch page (HTTP ' . $response->status() . ')'], 422);
            }

            $html = $response->body();
            $meta = [];

            if (preg_match('/<td>Author<\/td>\s*<td>(?:<a[^>]*>)?([^<]+)(?:<\/a>)?<\/td>/i', $html, $m)) {
                $author = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                if ($author) $meta['author'] = $author;
            }

            if (preg_match('/<td>Skin<\/td>\s*<td>([^<]+)<\/td>/i', $html, $m)) {
                $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                if ($name) $meta['name'] = $name;
            }

            if (empty($meta)) {
                return response()->json(['error' => 'No useful metadata found on page'], 422);
            }

            $updates = [];
            if (!empty($meta['author'])) $updates['author'] = $meta['author'];

            $model->update($updates);

            return response()->json([
                'success' => true,
                'updated' => $updates,
                'meta' => $meta,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Scrape failed: ' . $e->getMessage()], 500);
        }
    }
}
