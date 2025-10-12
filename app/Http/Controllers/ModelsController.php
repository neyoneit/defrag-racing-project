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

        $query = PlayerModel::with('user')
            ->approved()
            ->orderBy('created_at', 'desc');

        if ($category !== 'all') {
            $query->category($category);
        }

        $models = $query->paginate(24);

        return Inertia::render('Models/Index', [
            'models' => $models,
            'category' => $category,
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
                // Auto-detect model name from folder structure
                $detectedModelName = $this->detectModelName($extractPath);

                if (!$detectedModelName) {
                    $this->deleteDirectory($extractPath);
                    return back()->with('error', 'Could not find model folder. Make sure the PK3 contains a models/players/{name}/ directory.');
                }

                // Use detected name, or fallback to user input
                $finalModelName = $detectedModelName ?? $modelName;

                // Parse metadata and available skins
                $metadata = $this->parseModelMetadata($extractPath, $detectedModelName);

                // Create model record
                $model = PlayerModel::create([
                    'user_id' => $userId,
                    'name' => $finalModelName,
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
                    'available_skins' => json_encode($metadata['available_skins'] ?? ['default']),
                    'approved' => false, // Requires admin approval
                ]);

                return redirect()->route('models.show', $model->id)
                    ->with('success', 'Model "' . $finalModelName . '" uploaded successfully! It will be visible once approved by an admin.');
            }
        }

        return back()->with('error', 'Failed to extract model file. Make sure it\'s a valid PK3 or ZIP containing a PK3.');
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
     * Display the specified model
     */
    public function show(Request $request, $id)
    {
        $model = PlayerModel::with('user')->findOrFail($id);

        // Only show approved models unless user is the owner or admin
        if (!$model->approved && (!Auth::check() || (Auth::id() !== $model->user_id && !Auth::user()->is_admin))) {
            abort(404);
        }

        // For thumbnail generation, render without layout
        if ($request->has('thumbnail')) {
            return Inertia::render('Models/ShowThumbnail', [
                'model' => $model,
            ]);
        }

        return Inertia::render('Models/Show', [
            'model' => $model,
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
