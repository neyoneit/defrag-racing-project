<?php

namespace App\Console\Commands;

use App\Models\PlayerModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ScrapeQ3dfModels extends Command
{
    protected $signature = 'scrape:q3df-models {--limit=50 : Number of models to download} {--user-id=8 : User ID to assign models to} {--pages=1 : Number of pages to scrape} {--start-page=220 : Page number to start from (220 is oldest)} {--reverse : Go backwards from start-page (for oldest first)}';
    protected $description = 'Download and import PK3 files from ws.q3df.org (use --start-page=220 --reverse for oldest models first)';

    private $processedPk3Files = [];

    public function handle()
    {
        $this->info('Fetching models from ws.q3df.org...');

        try {
            $userId = (int) $this->option('user-id');
            $pages = (int) $this->option('pages');
            $startPage = (int) $this->option('start-page');
            $reverse = $this->option('reverse');

            // Load previously downloaded files list
            $downloadedHistoryPath = 'bulk-upload/downloaded_history.json';
            $downloadedHistory = [];
            if (Storage::exists($downloadedHistoryPath)) {
                $downloadedHistory = json_decode(Storage::get($downloadedHistoryPath), true) ?? [];
                $this->info('Loaded history: ' . count($downloadedHistory) . ' previously downloaded files');
            }

            // Fetch multiple pages
            $allModels = [];
            for ($i = 0; $i < $pages; $i++) {
                // Calculate page number based on direction
                if ($reverse) {
                    $page = $startPage - $i; // Go backwards from start page
                    if ($page < 0) break; // Stop if we reach before page 0
                } else {
                    $page = $startPage + $i; // Go forwards from start page
                }

                $url = $page === 0 ? 'https://ws.q3df.org/models/?show=50' : "https://ws.q3df.org/models/?model=&page={$page}&show=50";
                $this->info("Fetching page " . ($page + 1) . " (page index {$page})...");

                $response = Http::get($url);

                if (!$response->successful()) {
                    $this->error("Failed to fetch page " . ($page + 1));
                    continue;
                }

                $html = $response->body();

                // Parse the HTML to extract model information
                $pageModels = $this->parseModelsPage($html);
                $this->info("Found " . count($pageModels) . " models on page " . ($page + 1));

                $allModels = array_merge($allModels, $pageModels);

                // Small delay between page requests to be nice to the server
                if ($i < $pages - 1) {
                    sleep(1);
                }
            }

            $models = $allModels;
            $this->info('Total models found: ' . count($models));

            $limit = (int) $this->option('limit');
            $processed = 0;
            $imported = 0;
            $skipped = 0;
            $failed = 0;
            $failedModels = []; // Track failed models for summary

            $this->info("\n🔍 Processing " . count($models) . " models (limit: {$limit})...\n");

            foreach ($models as $modelData) {
                if ($imported >= $limit) {
                    break;
                }

                // Skip base Q3 pak files (pak0.pk3 through pak8.pk3)
                if (preg_match('/^pak[0-8]\.pk3$/i', $modelData['pk3_file'])) {
                    $this->line("Skipping base Q3 pak file: {$modelData['pk3_file']}");
                    $skipped++;
                    continue;
                }

                // Skip if already downloaded in previous runs (check history file only)
                // Note: This is just an optimization - actual duplicate detection happens at model+skin level
                if (isset($downloadedHistory[$modelData['pk3_file']])) {
                    $this->line("Already imported (history): {$modelData['pk3_file']}");
                    $skipped++;
                    continue;
                }

                // Skip if we already processed this PK3 file in this run
                if (in_array($modelData['pk3_file'], $this->processedPk3Files)) {
                    $this->line("Skipping duplicate PK3 in this run: {$modelData['pk3_file']}");
                    $skipped++;
                    continue;
                }

                $this->processedPk3Files[] = $modelData['pk3_file'];
                $processed++;

                $importCount = $imported + 1;
                $this->info("[{$importCount}/{$limit}] Processing: {$modelData['pk3_file']}");

                // Download and import the PK3 file
                try {
                    $result = $this->downloadAndImportPk3($modelData, $userId);

                    if ($result['success']) {
                        $imported++;

                        // Add to history
                        $downloadedHistory[$modelData['pk3_file']] = [
                            'downloaded_at' => date('Y-m-d H:i:s'),
                            'author' => $modelData['author'],
                            'model_name' => $modelData['name'],
                            'model_id' => $result['model_id'],
                        ];

                        $this->info("  ✓ Imported: {$result['model_name']} (ID: {$result['model_id']})");
                    } else {
                        $failed++;
                        $failedModels[] = ['pk3' => $modelData['pk3_file'], 'error' => $result['error']];
                        $this->error("  ✗ Failed: {$result['error']}");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $errorMsg = $e->getMessage();
                    // Log full error for debugging
                    \Log::error("Scraper error for {$modelData['pk3_file']}", [
                        'error' => $errorMsg,
                        'trace' => $e->getTraceAsString()
                    ]);
                    $failedModels[] = ['pk3' => $modelData['pk3_file'], 'error' => $errorMsg];
                    $this->error("  ✗ Error: " . $errorMsg);
                }
            }

            // Save downloaded history
            Storage::put($downloadedHistoryPath, json_encode($downloadedHistory, JSON_PRETTY_PRINT));

            // Append failed models to persistent log file
            if (!empty($failedModels)) {
                $failedLogPath = 'bulk-upload/failed_models.log';
                $timestamp = now()->format('Y-m-d H:i:s');
                $logEntries = [];

                foreach ($failedModels as $failedModel) {
                    $logEntries[] = "[{$timestamp}] {$failedModel['pk3']}: {$failedModel['error']}";
                }

                // Append to existing log file
                if (Storage::exists($failedLogPath)) {
                    $existingLog = Storage::get($failedLogPath);
                    Storage::put($failedLogPath, $existingLog . "\n" . implode("\n", $logEntries));
                } else {
                    Storage::put($failedLogPath, implode("\n", $logEntries));
                }
            }

            $this->info("\n=== Import Summary ===");
            $this->info("✅ Successfully imported: {$imported} models");
            $this->info("⏭️  Skipped: {$skipped} files (already imported or duplicates)");
            $this->info("❌ Failed: {$failed} models");
            $this->info("📊 Total in history: " . count($downloadedHistory) . " files");

            // Show failed models for debugging
            if (!empty($failedModels)) {
                $this->error("\n=== Failed Models (for debugging) ===");
                foreach ($failedModels as $failedModel) {
                    $this->error("❌ {$failedModel['pk3']}: {$failedModel['error']}");
                }
                $this->info("\n💾 Failed models logged to: storage/app/{$failedLogPath}");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function parseModelsPage($html)
    {
        $models = [];

        // Parse each model item container
        // Structure: div.models_modelitem contains model name link, download link, etc
        preg_match_all('/<div class="models_modelitem">.*?<\/div>/is', $html, $containers, PREG_SET_ORDER);

        foreach ($containers as $container) {
            $containerHtml = $container[0];

            // Extract model detail page URL and name
            // Format: <a href="/model/major/skin/myriane/">Myriane</a>
            if (!preg_match('/<a href="(\/model\/[^"]+)">([^<]+)<\/a>/', $containerHtml, $modelMatch)) {
                continue;
            }

            $modelDetailUrl = 'https://ws.q3df.org' . $modelMatch[1];
            $modelName = trim($modelMatch[2]);

            // Extract download URL and filename
            // Format: <a href="/models/downloads/scorn-myriane_da202-redbluefix.zip">scorn-myriane_da202-redbluefix.zip</a>
            if (!preg_match('/<a href="(\/models\/downloads\/[^"]+\.(pk3|zip))">([^<]+)<\/a>/', $containerHtml, $downloadMatch)) {
                continue;
            }

            $pk3Url = 'https://ws.q3df.org' . $downloadMatch[1];
            $pk3File = trim($downloadMatch[3]);

            // Fetch the model detail page to get author
            $author = 'Unknown';
            try {
                $detailResponse = Http::timeout(10)->get($modelDetailUrl);
                if ($detailResponse->successful()) {
                    $detailHtml = $detailResponse->body();
                    // Extract author: <td>Author</td><td><a href="...">SCORN</a></td>
                    if (preg_match('/<td>Author<\/td>\s*<td><a[^>]*>([^<]+)<\/a><\/td>/i', $detailHtml, $authorMatch)) {
                        $author = trim($authorMatch[1]);
                    }
                }
            } catch (\Exception $e) {
                // If we can't fetch detail page, just continue with Unknown author
            }

            $models[] = [
                'name' => $modelName,
                'author' => $author,
                'pk3_file' => $pk3File,
                'pk3_url' => $pk3Url,
            ];
        }

        return $models;
    }

    private function downloadAndImportPk3($modelData, $userId)
    {
        try {
            // Download the PK3 file (3 minute timeout for large files, 3 retries)
            $response = Http::timeout(180)
                ->retry(3, 1000) // Retry 3 times with 1 second delay
                ->withOptions(['verify' => false]) // Skip SSL verification for ws.q3df.org
                ->get($modelData['pk3_url']);

            if (!$response->successful()) {
                $statusCode = $response->status();
                $errorBody = substr($response->body(), 0, 200); // First 200 chars of error
                \Log::error("Download failed for {$modelData['pk3_file']}", [
                    'url' => $modelData['pk3_url'],
                    'status' => $statusCode,
                    'error_body' => $errorBody
                ]);
                return ['success' => false, 'error' => "Failed to download PK3 file (HTTP {$statusCode})"];
            }

            // Verify we got actual content
            if ($response->body() === null || strlen($response->body()) === 0) {
                return ['success' => false, 'error' => 'Downloaded file is empty'];
            }

            $originalName = $modelData['pk3_file'];
            $slug = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . time() . '-' . rand(1000, 9999);

            // Extracted files go to PUBLIC storage
            $extractPath = storage_path('app/public/models/extracted/' . $slug);

            // Original PK3 goes to PRIVATE storage
            $pk3StoragePath = storage_path('app/models/pk3s');
            if (!file_exists($pk3StoragePath)) {
                mkdir($pk3StoragePath, 0755, true);
            }

            // Save temporarily
            $tempPath = 'models/temp/' . $slug . '.pk3';
            Storage::put($tempPath, $response->body());
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

                    // Accept PK3 files even if they're in subdirectories (e.g., baseq3/model.pk3)
                    if ($ext === 'pk3') {
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
                        $filenameLower = strtolower($filename);
                        if (strpos($filenameLower, 'models/players/') === 0 || strpos($filenameLower, 'sound/player/') === 0) {
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
                        return ['success' => false, 'error' => 'Could not find any model folders'];
                    }

                    $createdModels = [];
                    $isMultiModelPack = count($detectedModelNames) > 1;

                    // Create a separate database entry for each model+skin combination
                    foreach ($detectedModelNames as $detectedModelName) {
                        // Note: detectAllModelNames() already filtered out empty folders

                        // Parse metadata
                        $metadata = $this->parseModelMetadata($extractPath, $detectedModelName);
                        $hasMd3Files = $this->checkForMd3Files($extractPath, $detectedModelName);
                        $baseModel = $hasMd3Files ? $detectedModelName : $detectedModelName;
                        $modelType = $this->determineModelType($extractPath, $detectedModelName, $hasMd3Files, $metadata);

                        // Determine base model file path for MD3 files
                        $baseModelFilePath = $this->determineBaseModelFilePath($extractPath, $detectedModelName, $hasMd3Files, $baseModel);

                        // Use author from ws.q3df.org, fallback to metadata from txt file
                        $author = $modelData['author'] !== 'Unknown' ? $modelData['author'] : ($metadata['author'] ?? null);

                        // Get available skins for this model
                        $availableSkins = $metadata['available_skins'] ?? ['default'];

                        // Create a separate entry for each skin
                        foreach ($availableSkins as $skinName) {
                            // Skip skins without actual texture files
                            if (!$this->skinHasTextures($extractPath, $detectedModelName, $skinName)) {
                                $this->line("  Skipping empty skin: {$detectedModelName} ({$skinName})");
                                continue;
                            }

                            // Check if this base_model + skin combination already exists in database
                            $existingModel = PlayerModel::where('base_model', strtolower($baseModel))
                                ->whereRaw("JSON_CONTAINS(available_skins, '\"" . $skinName . "\"')")
                                ->first();

                            if ($existingModel) {
                                $this->line("  Skipping duplicate: {$baseModel} ({$skinName}) - already exists as ID {$existingModel->id}");
                                continue;
                            }
                            // Determine display name:
                            // For multi-model packs: use "{ModelName} ({skin})"
                            // For single-model packs with one skin: use name from ws.q3df.org
                            // For single-model packs with multiple skins: use "{ws.q3df.org name} ({skin})"
                            if ($isMultiModelPack) {
                                $displayName = ucfirst($detectedModelName) . ' (' . $skinName . ')';
                            } else {
                                // Single model pack
                                if (count($availableSkins) > 1) {
                                    $displayName = $modelData['name'] . ' (' . $skinName . ')';
                                } else {
                                    $displayName = $modelData['name'];
                                }
                            }

                            // Construct file_path with actual case-sensitive folder names
                            // This allows the frontend to correctly locate files in folders like "Models/Players" vs "models/players"
                            $actualModelsFolder = $metadata['actual_models_folder'] ?? 'models';
                            $actualPlayersFolder = $metadata['actual_players_folder'] ?? 'players';
                            $filePathWithCase = 'models/extracted/' . $slug . '/' . $actualModelsFolder . '/' . $actualPlayersFolder . '/' . $baseModel;

                            // Create model record
                            $model = PlayerModel::create([
                                'user_id' => $userId,
                                'name' => $displayName,
                                'base_model' => $baseModel,
                                'base_model_file_path' => $baseModelFilePath,
                                'model_type' => $modelType,
                                'description' => $author ? "Created by {$author}" : null,
                                'category' => 'player', // Default to player
                                'author' => $author,
                                'author_email' => $metadata['author_email'] ?? null,
                                'file_path' => $filePathWithCase,
                                'zip_path' => $pk3PathForDownload,
                                'poly_count' => $metadata['poly_count'] ?? null,
                                'vert_count' => $metadata['vert_count'] ?? null,
                                'has_sounds' => $metadata['has_sounds'] ?? false,
                                'has_ctf_skins' => $metadata['has_ctf_skins'] ?? false,
                                'available_skins' => json_encode([$skinName]), // Store only this skin
                                'approval_status' => 'approved', // Auto-approve scraper imports
                            ]);

                            $createdModels[] = [
                                'id' => $model->id,
                                'name' => $model->name,
                            ];
                        }
                    }

                    // Check if any models were actually created
                    if (empty($createdModels)) {
                        return ['success' => false, 'error' => 'No valid models/skins found in PK3 (all were empty or duplicates)'];
                    }

                    return [
                        'success' => true,
                        'model_id' => $createdModels[0]['id'], // Return first model ID for history
                        'model_name' => implode(', ', array_column($createdModels, 'name')),
                        'created_count' => count($createdModels),
                    ];
                } else {
                    return ['success' => false, 'error' => 'Not a valid PK3 file'];
                }
            }

            return ['success' => false, 'error' => 'Failed to open archive'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function detectModelName($extractPath)
    {
        $playersPath = $extractPath . '/models/players';

        if (!is_dir($playersPath)) {
            return null;
        }

        $dirs = array_diff(scandir($playersPath), ['.', '..']);

        foreach ($dirs as $dir) {
            if (is_dir($playersPath . '/' . $dir)) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Detect ALL model names in a PK3 (for multi-model packs)
     */
    private function detectAllModelNames($extractPath)
    {
        // Find models/players folder (case-insensitive)
        $playersPath = null;
        $modelsPath = null;

        // Check for models folder (case-insensitive)
        if (is_dir($extractPath)) {
            $dirs = array_diff(scandir($extractPath), ['.', '..']);
            foreach ($dirs as $dir) {
                if (strcasecmp($dir, 'models') === 0 && is_dir($extractPath . '/' . $dir)) {
                    $modelsPath = $extractPath . '/' . $dir;
                    break;
                }
            }
        }

        if (!$modelsPath) {
            return [];
        }

        // Check for players folder (case-insensitive)
        $playersDirs = array_diff(scandir($modelsPath), ['.', '..']);
        foreach ($playersDirs as $dir) {
            if (strcasecmp($dir, 'players') === 0 && is_dir($modelsPath . '/' . $dir)) {
                $playersPath = $modelsPath . '/' . $dir;
                break;
            }
        }

        if (!$playersPath) {
            return [];
        }

        $dirs = array_diff(scandir($playersPath), ['.', '..']);
        $modelNames = [];
        $seenLowercase = []; // Track lowercase versions to avoid case-insensitive duplicates

        foreach ($dirs as $dir) {
            if (is_dir($playersPath . '/' . $dir)) {
                $lowerDir = strtolower($dir);

                // Skip if we've already seen this name (case-insensitive)
                if (in_array($lowerDir, $seenLowercase)) {
                    continue;
                }

                // Check if this folder has any actual content before adding it
                // This filters out empty placeholder folders
                $files = glob($playersPath . '/' . $dir . '/*.{skin,tga,jpg,png,md3,MD3,TGA,JPG,PNG,shader,shaderx}', GLOB_BRACE);

                if (!empty($files)) {
                    $modelNames[] = $dir;
                    $seenLowercase[] = $lowerDir;
                }
            }
        }

        return $modelNames;
    }

    private function checkForMd3Files($extractPath, $modelName)
    {
        // Find model path (case-insensitive)
        $modelPath = null;

        // First find models folder
        $modelsPath = null;
        if (is_dir($extractPath)) {
            $dirs = array_diff(scandir($extractPath), ['.', '..']);
            foreach ($dirs as $dir) {
                if (strcasecmp($dir, 'models') === 0 && is_dir($extractPath . '/' . $dir)) {
                    $modelsPath = $extractPath . '/' . $dir;
                    break;
                }
            }
        }

        if (!$modelsPath) {
            return false;
        }

        // Then find players folder
        $playersPath = null;
        $playersDirs = array_diff(scandir($modelsPath), ['.', '..']);
        foreach ($playersDirs as $dir) {
            if (strcasecmp($dir, 'players') === 0 && is_dir($modelsPath . '/' . $dir)) {
                $playersPath = $modelsPath . '/' . $dir;
                break;
            }
        }

        if (!$playersPath) {
            return false;
        }

        // Find actual model folder (case-insensitive)
        $modelDirs = array_diff(scandir($playersPath), ['.', '..']);
        foreach ($modelDirs as $dir) {
            if (strcasecmp($dir, $modelName) === 0 && is_dir($playersPath . '/' . $dir)) {
                $modelPath = $playersPath . '/' . $dir;
                break;
            }
        }

        if (!$modelPath || !is_dir($modelPath)) {
            return false;
        }

        // Check for MD3 files (case-insensitive)
        $files = array_diff(scandir($modelPath), ['.', '..']);
        $hasHead = false;
        $hasUpper = false;
        $hasLower = false;

        foreach ($files as $file) {
            if (strcasecmp($file, 'head.md3') === 0) {
                $hasHead = true;
            } elseif (strcasecmp($file, 'upper.md3') === 0) {
                $hasUpper = true;
            } elseif (strcasecmp($file, 'lower.md3') === 0) {
                $hasLower = true;
            }
        }

        return $hasHead && $hasUpper && $hasLower;
    }

    private function determineModelType($extractPath, $modelName, $hasMd3Files, $metadata)
    {
        if ($hasMd3Files) {
            return 'complete';
        }

        $hasSkins = false;
        $hasSounds = $metadata['has_sounds'] ?? false;
        $hasShaders = false;

        // Find model path (case-insensitive)
        $modelPath = null;

        // First find models folder
        $modelsPath = null;
        if (is_dir($extractPath)) {
            $dirs = array_diff(scandir($extractPath), ['.', '..']);
            foreach ($dirs as $dir) {
                if (strcasecmp($dir, 'models') === 0 && is_dir($extractPath . '/' . $dir)) {
                    $modelsPath = $extractPath . '/' . $dir;
                    break;
                }
            }
        }

        if ($modelsPath) {
            // Then find players folder
            $playersPath = null;
            $playersDirs = array_diff(scandir($modelsPath), ['.', '..']);
            foreach ($playersDirs as $dir) {
                if (strcasecmp($dir, 'players') === 0 && is_dir($modelsPath . '/' . $dir)) {
                    $playersPath = $modelsPath . '/' . $dir;
                    break;
                }
            }

            if ($playersPath) {
                // Find actual model folder (case-insensitive)
                $modelDirs = array_diff(scandir($playersPath), ['.', '..']);
                foreach ($modelDirs as $dir) {
                    if (strcasecmp($dir, $modelName) === 0 && is_dir($playersPath . '/' . $dir)) {
                        $modelPath = $playersPath . '/' . $dir;
                        break;
                    }
                }
            }
        }

        if ($modelPath && is_dir($modelPath)) {
            $skinFiles = glob($modelPath . '/*.skin');
            $textureFiles = array_merge(
                glob($modelPath . '/*.tga') ?: [],
                glob($modelPath . '/*.jpg') ?: [],
                glob($modelPath . '/*.jpeg') ?: [],
                glob($modelPath . '/*.png') ?: []
            );
            $hasSkins = !empty($skinFiles) || !empty($textureFiles);
        }

        $scriptsPath = $extractPath . '/scripts';
        if (is_dir($scriptsPath)) {
            $shaderFiles = array_merge(
                glob($scriptsPath . '/*.shader') ?: [],
                glob($scriptsPath . '/*.shaderx') ?: []
            );
            $hasShaders = !empty($shaderFiles);
        }

        if ($hasSkins && !$hasSounds && !$hasShaders) {
            return 'skin';
        } elseif ($hasSounds && !$hasSkins && !$hasShaders) {
            return 'sound';
        } else {
            return 'mixed';
        }
    }

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
            'actual_models_folder' => null, // Store actual case-sensitive folder name (e.g., "Models" or "models")
            'actual_players_folder' => null, // Store actual case-sensitive folder name (e.g., "Players" or "players")
        ];

        $files = glob($extractPath . '/*.{txt,TXT}', GLOB_BRACE);
        if (!empty($files)) {
            $content = file_get_contents($files[0]);

            if (preg_match('/Author\s*:?\s*(.+)/i', $content, $matches)) {
                $metadata['author'] = trim($matches[1]);
            }

            if (preg_match('/Email.*?:?\s*(.+@.+\..+)/i', $content, $matches)) {
                $metadata['author_email'] = trim($matches[1]);
            }

            if (preg_match('/Poly Count\s*:?\s*(\d+)/i', $content, $matches)) {
                $metadata['poly_count'] = (int)$matches[1];
            }

            if (preg_match('/Vert Count\s*:?\s*(\d+)/i', $content, $matches)) {
                $metadata['vert_count'] = (int)$matches[1];
            }

            if (preg_match('/New Sounds\s*:?\s*yes/i', $content)) {
                $metadata['has_sounds'] = true;
            }

            if (preg_match('/CTF Skins\s*:?\s*yes/i', $content)) {
                $metadata['has_ctf_skins'] = true;
            }
        }

        if (is_dir($extractPath . '/sound')) {
            $metadata['has_sounds'] = true;
        }

        // Find model path (case-insensitive), prioritize non-empty folders
        // First find models folder
        $modelsPath = null;
        $actualModelsFolder = null;
        if (is_dir($extractPath)) {
            $dirs = array_diff(scandir($extractPath), ['.', '..']);
            foreach ($dirs as $dir) {
                if (strcasecmp($dir, 'models') === 0 && is_dir($extractPath . '/' . $dir)) {
                    $modelsPath = $extractPath . '/' . $dir;
                    $actualModelsFolder = $dir; // Store actual case (e.g., "Models" or "models")
                    break;
                }
            }
        }

        if (!$modelsPath) {
            return $metadata; // No models folder found
        }

        // Then find players folder
        $playersPath = null;
        $actualPlayersFolder = null;
        $playersDirs = array_diff(scandir($modelsPath), ['.', '..']);
        foreach ($playersDirs as $dir) {
            if (strcasecmp($dir, 'players') === 0 && is_dir($modelsPath . '/' . $dir)) {
                $playersPath = $modelsPath . '/' . $dir;
                $actualPlayersFolder = $dir; // Store actual case (e.g., "Players" or "players")
                break;
            }
        }

        // Store the actual folder names
        $metadata['actual_models_folder'] = $actualModelsFolder;
        $metadata['actual_players_folder'] = $actualPlayersFolder;

        $actualModelName = null;

        if ($playersPath) {
            $dirs = array_diff(scandir($playersPath), ['.', '..']);
            $candidates = [];

            // Find all case-insensitive matches
            foreach ($dirs as $dir) {
                if (strcasecmp($dir, $modelName) === 0 && is_dir($playersPath . '/' . $dir)) {
                    $candidates[] = $dir;
                }
            }

            // If multiple matches, choose the one with actual files
            if (count($candidates) > 1) {
                foreach ($candidates as $candidate) {
                    $files = glob($playersPath . '/' . $candidate . '/*.{skin,tga,jpg,png,md3,shader,shaderx}', GLOB_BRACE);
                    if (!empty($files)) {
                        $actualModelName = $candidate;
                        break;
                    }
                }
            } elseif (count($candidates) === 1) {
                $actualModelName = $candidates[0];
            }
        }

        $modelPath = $actualModelName ? ($playersPath . '/' . $actualModelName) : ($extractPath . '/models/players/' . $modelName);

        if (is_dir($modelPath)) {
            $skinFiles = glob($modelPath . '/*.skin');
            $skins = [];

            foreach ($skinFiles as $skinFile) {
                $filename = basename($skinFile, '.skin');

                // Match either:
                // 1. modelname_skinname.skin (e.g., anarki_default.skin)
                // 2. lower_/upper_/head_skinname.skin (e.g., lower_Mystic_Surfer.skin)
                if (preg_match('/_(.+)$/', $filename, $matches)) {
                    $skinName = $matches[1];

                    // Skip if it's just "lower", "upper", or "head" alone (base model files)
                    if (in_array(strtolower($skinName), ['lower', 'upper', 'head'])) {
                        continue;
                    }

                    if (!in_array($skinName, $skins)) {
                        $skins[] = $skinName;
                    }
                }
            }

            if (!empty($skins)) {
                usort($skins, function($a, $b) {
                    if ($a === 'default') return -1;
                    if ($b === 'default') return 1;
                    return strcmp($a, $b);
                });

                $metadata['available_skins'] = $skins;

                if (in_array('red', $skins) && in_array('blue', $skins)) {
                    $metadata['has_ctf_skins'] = true;
                }
            }
        }

        return $metadata;
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
        return null;
    }

    /**
     * Check if a model folder has actual content (not empty)
     * A valid model folder should have at least one of: .skin files, texture files, .md3 files, or shaders
     */
    private function hasActualModelContent($extractPath, $modelName)
    {
        // Try both original case and lowercase (PK3s can have inconsistent casing)
        $playersPath = $extractPath . '/models/players';

        if (!is_dir($playersPath)) {
            return false;
        }

        // Find the actual folder name (case-insensitive match)
        $dirs = array_diff(scandir($playersPath), ['.', '..']);
        $actualModelName = null;

        foreach ($dirs as $dir) {
            if (strcasecmp($dir, $modelName) === 0 && is_dir($playersPath . '/' . $dir)) {
                $actualModelName = $dir;
                break;
            }
        }

        if (!$actualModelName) {
            return false;
        }

        $modelPath = $playersPath . '/' . $actualModelName;

        // Check for any .skin, texture, .md3, or shader files
        $files = glob($modelPath . '/*.{skin,tga,jpg,png,md3,MD3,TGA,JPG,PNG,shader,shaderx}', GLOB_BRACE);

        return !empty($files);
    }

    /**
     * Check if a specific skin has actual content
     * For 'default' skin: must have texture files (not just .skin file)
     * For custom skins: must have .skin file OR texture files OR shader files
     */
    private function skinHasTextures($extractPath, $modelName, $skinName)
    {
        // Find the actual folder name (case-insensitive), prioritize non-empty folders
        // First find models folder
        $modelsPath = null;
        if (is_dir($extractPath)) {
            $dirs = array_diff(scandir($extractPath), ['.', '..']);
            foreach ($dirs as $dir) {
                if (strcasecmp($dir, 'models') === 0 && is_dir($extractPath . '/' . $dir)) {
                    $modelsPath = $extractPath . '/' . $dir;
                    break;
                }
            }
        }

        if (!$modelsPath) {
            return false;
        }

        // Then find players folder
        $playersPath = null;
        $playersDirs = array_diff(scandir($modelsPath), ['.', '..']);
        foreach ($playersDirs as $dir) {
            if (strcasecmp($dir, 'players') === 0 && is_dir($modelsPath . '/' . $dir)) {
                $playersPath = $modelsPath . '/' . $dir;
                break;
            }
        }

        if (!$playersPath) {
            return false;
        }

        $dirs = array_diff(scandir($playersPath), ['.', '..']);
        $actualModelName = null;
        $candidates = [];

        // Find all case-insensitive matches
        foreach ($dirs as $dir) {
            if (strcasecmp($dir, $modelName) === 0 && is_dir($playersPath . '/' . $dir)) {
                $candidates[] = $dir;
            }
        }

        // If multiple matches, choose the one with actual files
        if (count($candidates) > 1) {
            foreach ($candidates as $candidate) {
                $files = glob($playersPath . '/' . $candidate . '/*.{skin,tga,jpg,png,md3,shader,shaderx}', GLOB_BRACE);
                if (!empty($files)) {
                    $actualModelName = $candidate;
                    break;
                }
            }
        } elseif (count($candidates) === 1) {
            $actualModelName = $candidates[0];
        }

        if (!$actualModelName) {
            return false;
        }

        $modelPath = $playersPath . '/' . $actualModelName;

        // For 'default' skin, it must have actual textures (not just a .skin file reference)
        if ($skinName === 'default') {
            $textures = glob($modelPath . '/*.{tga,jpg,png,TGA,JPG,PNG}', GLOB_BRACE);
            return count($textures) > 0;
        }

        // For custom skins, accept if they have:
        // 1. A .skin file (matching either modelname_skinname.skin OR lower_/upper_/head_skinname.skin), OR
        // 2. Texture files, OR
        // 3. Shader files
        // This allows skin-only packs that override textures

        // Try to find .skin file with case-insensitive name matching
        $skinFiles = glob($modelPath . '/*.skin', GLOB_BRACE);
        $hasSkinFile = false;
        foreach ($skinFiles as $skinFile) {
            $basename = basename($skinFile, '.skin');
            // Match either: modelname_skinname.skin OR lower_/upper_/head_skinname.skin
            if (strcasecmp($basename, $actualModelName . '_' . $skinName) === 0 ||
                stripos($basename, '_' . $skinName) !== false) {
                $hasSkinFile = true;
                break;
            }
        }

        $hasTextures = count(glob($modelPath . '/*.{tga,jpg,png,TGA,JPG,PNG}', GLOB_BRACE)) > 0;
        $hasShaders = count(glob($modelPath . '/*.{shader,shaderx}', GLOB_BRACE)) > 0;

        return $hasSkinFile || $hasTextures || $hasShaders;
    }

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
