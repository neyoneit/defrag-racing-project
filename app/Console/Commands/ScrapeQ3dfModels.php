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

                // Skip if already downloaded in previous runs
                if (isset($downloadedHistory[$modelData['pk3_file']])) {
                    $this->line("Already imported: {$modelData['pk3_file']}");
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
                $this->info("Processing [{$importCount}/{$limit}]: {$modelData['pk3_file']}");

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
                        $this->error("  ✗ Failed: {$result['error']}");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("  ✗ Error: " . $e->getMessage());
                }
            }

            // Save downloaded history
            Storage::put($downloadedHistoryPath, json_encode($downloadedHistory, JSON_PRETTY_PRINT));

            $this->info("\n=== Import Summary ===");
            $this->info("Successfully imported: {$imported} models");
            $this->info("Failed: {$failed} models");
            $this->info("Skipped: {$skipped} files (already imported or duplicates)");
            $this->info("Total in history: " . count($downloadedHistory) . " files");

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
            // Download the PK3 file
            $response = Http::timeout(60)->get($modelData['pk3_url']);

            if (!$response->successful()) {
                return ['success' => false, 'error' => 'Failed to download PK3 file'];
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
                        return ['success' => false, 'error' => 'Could not find any model folders'];
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

                        // Determine base model file path for MD3 files
                        $baseModelFilePath = $this->determineBaseModelFilePath($extractPath, $detectedModelName, $hasMd3Files, $baseModel);

                        // Use author from ws.q3df.org, fallback to metadata from txt file
                        $author = $modelData['author'] !== 'Unknown' ? $modelData['author'] : ($metadata['author'] ?? null);

                        // Get available skins for this model
                        $availableSkins = $metadata['available_skins'] ?? ['default'];

                        // Create a separate entry for each skin
                        foreach ($availableSkins as $skinName) {
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
                                'file_path' => 'models/extracted/' . $slug,
                                'zip_path' => $pk3PathForDownload,
                                'poly_count' => $metadata['poly_count'] ?? null,
                                'vert_count' => $metadata['vert_count'] ?? null,
                                'has_sounds' => $metadata['has_sounds'] ?? false,
                                'has_ctf_skins' => $metadata['has_ctf_skins'] ?? false,
                                'available_skins' => json_encode([$skinName]), // Store only this skin
                                'approved' => true, // Auto-approve scraper imports
                            ]);

                            $createdModels[] = [
                                'id' => $model->id,
                                'name' => $model->name,
                            ];
                        }
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

    private function checkForMd3Files($extractPath, $modelName)
    {
        $modelPath = $extractPath . '/models/players/' . $modelName;

        if (!is_dir($modelPath)) {
            return false;
        }

        $hasHead = file_exists($modelPath . '/head.md3');
        $hasUpper = file_exists($modelPath . '/upper.md3');
        $hasLower = file_exists($modelPath . '/lower.md3');

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

        $modelPath = $extractPath . '/models/players/' . $modelName;
        if (is_dir($modelPath)) {
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
            $shaderFiles = glob($scriptsPath . '/*.shader');
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

        $modelPath = $extractPath . '/models/players/' . $modelName;
        if (is_dir($modelPath)) {
            $skinFiles = glob($modelPath . '/*_*.skin');
            $skins = [];

            foreach ($skinFiles as $skinFile) {
                $filename = basename($skinFile, '.skin');
                if (preg_match('/_(.+)$/', $filename, $matches)) {
                    $skinName = $matches[1];
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
