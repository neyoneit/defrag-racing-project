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
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:player,weapon,shadow',
            'model_file' => 'required|file|max:51200', // 50MB max - PK3 files
        ]);

        $pk3File = $request->file('model_file');
        $userId = Auth::id();
        $modelName = $request->name;
        $slug = \Str::slug($modelName) . '-' . time();

        // Store original PK3 (PK3 is just a ZIP file)
        $pk3Path = $pk3File->storeAs('models/pk3s', $slug . '.pk3', 'public');

        // Extract PK3 (treat it as a ZIP file)
        $extractPath = storage_path('app/public/models/extracted/' . $slug);
        $zip = new ZipArchive;

        if ($zip->open(storage_path('app/public/' . $pk3Path)) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();

            // Parse metadata
            $metadata = $this->parseModelMetadata($extractPath);

            // Create model record
            $model = PlayerModel::create([
                'user_id' => $userId,
                'name' => $modelName,
                'description' => $request->description,
                'category' => $request->category,
                'author' => $metadata['author'] ?? null,
                'author_email' => $metadata['author_email'] ?? null,
                'file_path' => 'models/extracted/' . $slug,
                'zip_path' => $pk3Path,
                'poly_count' => $metadata['poly_count'] ?? null,
                'vert_count' => $metadata['vert_count'] ?? null,
                'has_sounds' => $metadata['has_sounds'] ?? false,
                'has_ctf_skins' => $metadata['has_ctf_skins'] ?? false,
                'approved' => false, // Requires admin approval
            ]);

            return redirect()->route('models.show', $model->id)
                ->with('success', 'Model uploaded successfully! It will be visible once approved by an admin.');
        }

        return back()->with('error', 'Failed to extract model file.');
    }

    /**
     * Display the specified model
     */
    public function show($id)
    {
        $model = PlayerModel::with('user')->findOrFail($id);

        // Only show approved models unless user is the owner or admin
        if (!$model->approved && (!Auth::check() || (Auth::id() !== $model->user_id && !Auth::user()->is_admin))) {
            abort(404);
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

        $filePath = storage_path('app/public/' . $model->zip_path);

        return response()->download($filePath, $model->name . '.zip');
    }

    /**
     * Parse model metadata from extracted files
     */
    private function parseModelMetadata($extractPath)
    {
        $metadata = [
            'author' => null,
            'author_email' => null,
            'poly_count' => null,
            'vert_count' => null,
            'has_sounds' => false,
            'has_ctf_skins' => false,
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

        return $metadata;
    }
}
