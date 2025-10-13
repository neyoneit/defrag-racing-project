<?php

namespace App\Http\Controllers;

use App\Models\PlayerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $category = $request->get('category', 'all');
        $sort = $request->get('sort', 'newest'); // newest or oldest

        $query = PlayerModel::with('user')
            ->approved();

        if ($category !== 'all') {
            $query->category($category);
        }

        // Apply sorting (use id as tiebreaker when created_at is same)
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'); // newest (default)
        }

        $models = $query->paginate(12);

        return Inertia::render('Models/Index', [
            'models' => $models,
            'category' => $category,
            'sort' => $sort,
        ]);
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
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:player,weapon,shadow',
            'model_file' => 'required|file|mimes:zip,pk3|max:51200', // 50MB max - ZIP or PK3 files
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

        // Store original file temporarily in private storage
        $tempPath = $uploadedFile->storeAs('models/temp', $slug . '.' . $uploadedFile->getClientOriginalExtension());
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
            $containsPK3 = false;
            $pk3FileName = null;

            \Log::info('Analyzing uploaded file:', ['numFiles' => $zip->numFiles]);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $basename = basename($filename);
                $ext = pathinfo($basename, PATHINFO_EXTENSION);
                $hasSlash = strpos($filename, '/');

                \Log::info('File in archive:', [
                    'filename' => $filename,
                    'basename' => $basename,
                    'ext' => $ext,
                    'hasSlash' => $hasSlash
                ]);

                // Check if this is a PK3 file (not in a subdirectory)
                if ($ext === 'pk3' && $hasSlash === false) {
                    $containsPK3 = true;
                    $pk3FileName = $filename;
                    \Log::info('Found PK3 file:', ['filename' => $pk3FileName]);
                    break;
                }
            }

            \Log::info('After scan:', ['containsPK3' => $containsPK3, 'pk3FileName' => $pk3FileName]);

            if ($containsPK3 && $pk3FileName) {
                // This is a ZIP containing a PK3 file
                // Extract ZIP to temp location to find PK3
                $tempExtract = storage_path('app/models/temp/' . $slug . '_extract');
                if (!file_exists($tempExtract)) {
                    mkdir($tempExtract, 0755, true);
                }

                $zip->extractTo($tempExtract);
                $zip->close();

                // Find the PK3 file
                $pk3File = $tempExtract . '/' . $pk3FileName;

                if (file_exists($pk3File)) {
                    $pk3Found = true;

                    // Store the PK3 in PRIVATE storage for downloads
                    $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                    copy($pk3File, storage_path('app/' . $pk3PathForDownload));

                    // Extract the PK3 contents to PUBLIC storage
                    $pk3Zip = new ZipArchive;
                    if ($pk3Zip->open($pk3File) === TRUE) {
                        $pk3Zip->extractTo($extractPath);
                        $pk3Zip->close();
                    } else {
                        \Log::error('Failed to open PK3 file for extraction: ' . $pk3File);
                    }
                } else {
                    \Log::error('PK3 file not found after extraction: ' . $pk3File);
                }

                // Clean up temp extraction
                $this->deleteDirectory($tempExtract);
            } else {
                // This is a direct PK3 file - check if it has the proper structure
                // A valid PK3 should have models/players/ or sound/player/ directories
                $hasProperStructure = false;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (strpos($filename, 'models/players/') === 0 || strpos($filename, 'sound/player/') === 0) {
                        $hasProperStructure = true;
                        break;
                    }
                }

                if ($hasProperStructure) {
                    // This is a direct PK3 file with proper structure
                    $zip->extractTo($extractPath);
                    $zip->close();
                    $pk3Found = true;

                    // Store the PK3 in PRIVATE storage for downloads
                    $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                    copy($tempFullPath, storage_path('app/' . $pk3PathForDownload));
                } else {
                    $zip->close();
                }
            }

            // Clean up temp file
            Storage::delete($tempPath);

            if ($pk3Found) {
                // Auto-detect ALL model names (PK3 might contain multiple models)
                $detectedModelNames = $this->detectAllModelNames($extractPath);

                if (empty($detectedModelNames)) {
                    $this->deleteDirectory($extractPath);
                    return back()->with('error', 'Could not find any model folders. Make sure the PK3 contains models/players/{name}/ directories.');
                }

                $createdModels = [];
                $isMultiModelPack = count($detectedModelNames) > 1;

                // Create a separate database entry for each model+skin combination
                foreach ($detectedModelNames as $detectedModelName) {
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
                            'approved' => false, // Requires admin approval
                        ]);

                        $createdModels[] = $model;
                    }
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

                // Store original file temporarily
                $tempPath = $uploadedFile->storeAs('models/temp', $slug . '.' . $uploadedFile->getClientOriginalExtension());
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
                            }
                        }

                        $this->deleteDirectory($tempExtract);
                    } else {
                        // Direct PK3 file
                        $hasProperStructure = false;

                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (strpos($filename, 'models/players/') === 0 || strpos($filename, 'sound/player/') === 0) {
                                $hasProperStructure = true;
                                break;
                            }
                        }

                        if ($hasProperStructure) {
                            $zip->extractTo($extractPath);
                            $zip->close();
                            $pk3Found = true;

                            $pk3PathForDownload = 'models/pk3s/' . $slug . '.pk3';
                            copy($tempFullPath, storage_path('app/' . $pk3PathForDownload));
                        } else {
                            $zip->close();
                        }
                    }

                    Storage::delete($tempPath);

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
                                    'approved' => true, // Auto-approve for admin bulk uploads
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
     * Detect model name from extracted files
     * Looks for models/players/{name}/ directory
     */
    private function detectModelName($extractPath)
    {
        $playersPath = $extractPath . '/models/players';

        if (!is_dir($playersPath)) {
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
        $playersPath = $extractPath . '/models/players';

        if (!is_dir($playersPath)) {
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
     * Check if model directory contains MD3 files (complete model)
     * Returns true if has head.md3, upper.md3, lower.md3
     * Returns false if only has skins/textures (skin-only upload)
     */
    private function checkForMd3Files($extractPath, $modelName)
    {
        $modelPath = $extractPath . '/models/players/' . $modelName;

        if (!is_dir($modelPath)) {
            return false;
        }

        // Check for the three required MD3 files
        $hasHead = file_exists($modelPath . '/head.md3');
        $hasUpper = file_exists($modelPath . '/upper.md3');
        $hasLower = file_exists($modelPath . '/lower.md3');

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
        $modelPath = $extractPath . '/models/players/' . $modelName;
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
        $scriptsPath = $extractPath . '/scripts';
        if (is_dir($scriptsPath)) {
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
        $model = PlayerModel::with('user')->findOrFail($id);

        // Only show approved models unless user is the owner or admin
        if (!$model->approved && (!Auth::check() || (Auth::id() !== $model->user_id && !Auth::user()->is_admin))) {
            abort(404);
        }

        // For skin/mixed packs, fetch the base model data (for MD3 file paths)
        $baseModelData = null;
        if ($model->model_type !== 'complete' && $model->base_model && $model->base_model !== $model->name) {
            $baseModelData = PlayerModel::where('name', $model->base_model)
                ->where('model_type', 'complete')
                ->first(['name', 'file_path']);
        }

        // For thumbnail generation, render without layout
        if ($request->has('thumbnail')) {
            return Inertia::render('Models/ShowThumbnail', [
                'model' => $model,
                'baseModelData' => $baseModelData,
            ]);
        }

        return Inertia::render('Models/Show', [
            'model' => $model,
            'baseModelData' => $baseModelData,
        ]);
    }

    /**
     * Download the model
     */
    public function download($id)
    {
        $model = PlayerModel::findOrFail($id);

        if (!$model->approved) {
            abort(403);
        }

        $model->incrementDownloads();

        // PK3 files are stored in private storage (storage/app/models/pk3s/)
        $filePath = storage_path('app/' . $model->zip_path);

        if (!file_exists($filePath)) {
            abort(404, 'Model file not found');
        }

        return response()->download($filePath, $model->name . '.pk3');
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

        // Check for sound files
        if (is_dir($extractPath . '/sound')) {
            $metadata['has_sounds'] = true;
        }

        // Parse available skins from skin files
        $modelPath = $extractPath . '/models/players/' . $modelName;
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
     * Generate animated GIF thumbnail for a model
     */
    public function generateThumbnail($id)
    {
        $model = PlayerModel::findOrFail($id);

        // Check permission - only owner or admin
        if (!Auth::check() || (Auth::id() !== $model->user_id && !Auth::user()->admin)) {
            abort(403);
        }

        try {
            $framesDir = storage_path("app/temp/model_{$id}_frames");

            // Create frames directory
            if (!file_exists($framesDir)) {
                mkdir($framesDir, 0755, true);
            }

            // Render thumbnail using server-side Three.js
            $gifPath = storage_path("app/public/thumbnails/model_{$id}.gif");
            $thumbnailsDir = storage_path("app/public/thumbnails");

            if (!file_exists($thumbnailsDir)) {
                mkdir($thumbnailsDir, 0755, true);
            }

            $result = \Illuminate\Support\Facades\Process::timeout(120)->run([
                'node',
                base_path('renderModelThumbnail.cjs'),
                $id,
                $gifPath
            ]);

            if (!$result->successful()) {
                \Log::error("Failed to render thumbnail for model {$id}: " . $result->errorOutput());
                return response()->json(['error' => 'Failed to render thumbnail: ' . $result->errorOutput()], 500);
            }

            // Update model with thumbnail path
            $model->update(['thumbnail' => "thumbnails/model_{$id}.gif"]);

            return response()->json(['success' => true, 'thumbnail' => "thumbnails/model_{$id}.gif"]);

        } catch (\Exception $e) {
            \Log::error("Error generating thumbnail for model {$id}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
}
