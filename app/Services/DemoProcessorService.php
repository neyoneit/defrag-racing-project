<?php

namespace App\Services;

use App\Models\UploadedDemo;
use App\Models\Record;
use App\Models\OfflineRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DemoProcessorService
{
    protected $batchRenamerPath;

    public function __construct()
    {
        $this->batchRenamerPath = base_path('app/Services/DemoProcessor/bin/BatchDemoRenamer.py');
    }

    /**
     * Process uploaded demo file
     */
    public function processDemo(UploadedDemo $demo)
    {
        try {
            $demo->update(['status' => 'processing']);

            // Check if new Python implementation exists
            $processSingleScript = dirname($this->batchRenamerPath) . '/process_single_demo.py';
            if (!file_exists($processSingleScript)) {
                Log::warning('Python demo processor not found, skipping processing', [
                    'path' => $processSingleScript,
                    'demo_id' => $demo->id,
                ]);

                // Just mark as processed without renaming
                $demo->update([
                    'status' => 'processed',
                    'processing_output' => 'Python demo processor not available - file stored as-is',
                ]);

                return $demo;
            }

            // Create temp directory for processing
            $tempDir = storage_path('app/demos/processing/' . $demo->id);
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Copy demo to temp directory
            $tempFile = $tempDir . '/' . $demo->original_filename;
            copy($demo->full_path, $tempFile);

            // Run BatchDemoRenamer
            $output = $this->runBatchDemoRenamer($tempFile);

            // Parse the output to extract metadata
            $metadata = $this->parseRenamerOutput($output);

            // Use suggested filename if available, otherwise keep original
            $processedFilename = $metadata['suggested_filename'] ?? $demo->original_filename;

            // Compress the demo file to save space (but don't upload yet)
            $compressedLocalPath = $this->compressDemo($tempFile, $processedFilename);

            // Generate the proper compressed filename for storage
            $format = config('app.demo_compression_format', '7z');
            $compressedFilename = pathinfo($processedFilename, PATHINFO_FILENAME) . '.' . $format;

            // Determine status based on validity
            // If demo has ANY validity issues, mark as failed-validity
            Log::info('Checking validity for demo', [
                'demo_id' => $demo->id,
                'validity' => $metadata['validity'] ?? 'NULL',
                'validity_type' => gettype($metadata['validity'] ?? null),
            ]);
            $hasValidityIssues = !empty($metadata['validity']);
            $status = $hasValidityIssues ? 'failed-validity' : 'processed';
            Log::info('Status determined', [
                'demo_id' => $demo->id,
                'hasValidityIssues' => $hasValidityIssues,
                'status' => $status,
            ]);

            // Update demo record with metadata
            $demo->update([
                'processed_filename' => $compressedFilename,
                'file_path' => null, // Will be set after upload if successful
                'map_name' => $metadata['map'] ?? null,
                'physics' => $metadata['physics'] ?? null,
                'gametype' => $metadata['gametype'] ?? null,
                'time_ms' => $metadata['time_ms'] ?? null,
                'player_name' => $metadata['player'] ?? null,
                'country' => $metadata['country'] ?? null,
                'record_date' => $metadata['record_date'] ?? null,
                'validity' => $metadata['validity'] ?? null,
                'processing_output' => $output,
                'status' => $status,
            ]);

            // Clean up temp files from storage/app/demos/temp/{demo_id}/
            $originalTempDir = storage_path("app/demos/temp/{$demo->id}");
            if (is_dir($originalTempDir)) {
                array_map('unlink', glob($originalTempDir . '/*'));
                rmdir($originalTempDir);
            }

            // Clean up processing temp files
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);

            // Try to auto-assign to record (online) or create offline record
            // Ensure we have the latest attributes from DB after the update call
            $demo = $demo->fresh();

            Log::info('DEBUG: After fresh(), checking status', [
                'demo_id' => $demo->id,
                'status' => $demo->status,
                'is_offline' => $demo->is_offline,
                'will_skip_to_validity_branch' => $demo->status === 'failed-validity',
            ]);

            if ($demo->status !== 'failed-validity') {
                if ($demo->is_offline) {
                    // Create offline record for offline demos
                    $this->createOfflineRecord($demo, $compressedLocalPath);
                } else {
                    // Auto-assign online demos to existing records
                    $this->autoAssignToRecord($demo, $compressedLocalPath);
                }
            } else {
                // For failed-validity demos, create offline record with validity flags
                // These will appear in the leaderboard but with validity issues marked
                $this->createOfflineRecord($demo, $compressedLocalPath, useValidityAsFlag: true);

                Log::info('Created offline record for demo with validity issues', [
                    'demo_id' => $demo->id,
                    'validity' => $demo->validity,
                ]);
            }

            return $demo;

        } catch (\Exception $e) {
            Log::error('Demo processing failed: ' . $e->getMessage(), [
                'demo_id' => $demo->id,
                'error' => $e->getMessage(),
            ]);

            // Move the raw demo file to failed directory for admin review
            $this->moveToFailedDirectory($demo);

            $demo->update([
                'status' => 'failed',
                'processing_output' => $e->getMessage(),
            ]);

            // Clean up temp files on error
            if (isset($tempDir) && file_exists($tempDir)) {
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);
            }

            throw $e;
        }
    }

    /**
     * Run Python demo processor on a demo file
     */
    protected function runBatchDemoRenamer($filepath)
    {
        // Use new Python implementation with JSON output for full metadata
        $processSingleScript = dirname($this->batchRenamerPath) . '/process_single_demo.py';

        // Run the Python script with --json flag to get full metadata including record date
        $command = 'cd ' . escapeshellarg(dirname($this->batchRenamerPath)) . ' && ';
        $command .= 'python3 -W ignore ' . escapeshellarg($processSingleScript) . ' ' . escapeshellarg($filepath) . ' --json 2>/dev/null';

        $output = shell_exec($command);

        if ($output === null) {
            throw new \Exception('Failed to execute Python demo processor');
        }

        // Return the JSON output directly - parseRenamerOutput will handle it
        $trimmed = trim($output);
        if (!empty($trimmed) && !str_contains($trimmed, 'Error:')) {
            return $trimmed;
        } else {
            throw new \Exception('Demo processing failed: ' . $output);
        }
    }

    /**
     * Parse BatchDemoRenamer output to extract metadata
     * Supports both JSON (new format) and XML (legacy format)
     */
    protected function parseRenamerOutput($output)
    {
        $metadata = [];

        // Try parsing as JSON first (new format with --json flag)
        $jsonData = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['suggested_filename'])) {
            $metadata['suggested_filename'] = $jsonData['suggested_filename'];
            $metadata['record_date'] = $jsonData['record_date'] ?? null;
            $metadata['validity'] = $jsonData['validity'] ?? null;

            // Use fields directly from JSON when available (more reliable than regex parsing)
            if (isset($jsonData['map_name'])) {
                $metadata['map'] = $jsonData['map_name'];
            }
            if (isset($jsonData['player_name'])) {
                $metadata['player'] = $jsonData['player_name'];
            }
            if (isset($jsonData['physics'])) {
                // Physics format from Python: "mdf.cpm" or "mdf.vq3.tr"
                // Extract just the gameplay physics (CPM or VQ3) for the physics field
                $physicsParts = explode('.', strtoupper($jsonData['physics']));
                // The gameplay physics is the second part (index 1): MDF.CPM -> CPM, MDF.VQ3 -> VQ3
                $metadata['physics'] = $physicsParts[1] ?? strtoupper($jsonData['physics']);
                // If there's a third part (.TR), append it: CPM.TR or VQ3.TR
                if (isset($physicsParts[2])) {
                    $metadata['physics'] .= '.' . $physicsParts[2];
                }
                Log::info('DEBUG: Physics parsed from JSON', [
                    'input' => $jsonData['physics'],
                    'parts' => $physicsParts,
                    'result' => $metadata['physics'],
                ]);
            }
            if (isset($jsonData['time_seconds'])) {
                $metadata['time_ms'] = (int)($jsonData['time_seconds'] * 1000);
            }
            if (isset($jsonData['country'])) {
                $metadata['country'] = $jsonData['country'];
            }

            // Parse the suggested filename to extract remaining components (gametype)
            // and as fallback if JSON fields are missing
            $suggestedName = $jsonData['suggested_filename'];
            if (preg_match('/([^[]+)\[([^.]+)\.([^\]]+)\](\d+)\.(\d+)\.(\d+)\(([^)]+)\)\.dm_\d+/', $suggestedName, $matches)) {
                // Only set if not already from JSON (JSON is more reliable)
                if (!isset($metadata['map'])) {
                    $metadata['map'] = $matches[1];
                }
                if (!isset($metadata['gametype'])) {
                    $metadata['gametype'] = $matches[2]; // mdf, df, etc.
                }
                if (!isset($metadata['physics'])) {
                    $metadata['physics'] = strtoupper($matches[3]); // VQ3 or CPM
                    Log::info('DEBUG: Physics set from filename fallback', [
                        'physics' => $metadata['physics'],
                    ]);
                } else {
                    Log::info('DEBUG: Physics already set from JSON, not using fallback', [
                        'existing_physics' => $metadata['physics'],
                        'filename_would_be' => strtoupper($matches[3]),
                    ]);
                }

                // Calculate time in milliseconds (fallback if not from JSON)
                if (!isset($metadata['time_ms'])) {
                    $minutes = (int)$matches[4];
                    $seconds = (int)$matches[5];
                    $milliseconds = (int)$matches[6];
                    $metadata['time_ms'] = ($minutes * 60000) + ($seconds * 1000) + $milliseconds;
                }

                // Extract player name from filename if not from JSON
                if (!isset($metadata['player'])) {
                    $playerAndCountry = $matches[7];
                    $lastDotPos = strrpos($playerAndCountry, '.');
                    if ($lastDotPos !== false) {
                        $metadata['player'] = substr($playerAndCountry, 0, $lastDotPos);
                        // Only use country from filename if not already set from JSON
                        if (!isset($metadata['country'])) {
                            $metadata['country'] = substr($playerAndCountry, $lastDotPos + 1);
                        }
                    } else {
                        // No country in filename
                        $metadata['player'] = $playerAndCountry;
                        if (!isset($metadata['country'])) {
                            $metadata['country'] = null;
                        }
                    }
                }
            }

            // Extract gametype from physics if not already set
            // Format is like "mdf.vq3" where mdf is gametype
            if (!isset($metadata['gametype']) && isset($jsonData['physics'])) {
                $physicsParts = explode('.', $jsonData['physics']);
                if (count($physicsParts) >= 2) {
                    $metadata['gametype'] = $physicsParts[0];
                }
            }

            return $metadata;
        }

        // Fallback to XML parsing (legacy format)
        if (preg_match('/<demoFile>.*<\/demoFile>/s', $output, $xmlMatch)) {
            try {
                $xml = simplexml_load_string($xmlMatch[0]);

                // Get the suggested filename
                if (isset($xml->fileName['suggestedFileName'])) {
                    $suggestedName = (string)$xml->fileName['suggestedFileName'];
                    $metadata['suggested_filename'] = $suggestedName;

                    // Parse the suggested filename to extract components
                    // Format: mapname[gametype.physics]mm.ss.mmm(player).dm_68
                    if (preg_match('/([^[]+)\[([^.]+)\.([^\]]+)\](\d+)\.(\d+)\.(\d+)\(([^)]+)\)\.dm_\d+/', $suggestedName, $matches)) {
                        $metadata['map'] = $matches[1];
                        $metadata['gametype'] = $matches[2]; // mdf, df, etc.
                        $metadata['physics'] = strtoupper($matches[3]); // VQ3 or CPM

                        // Calculate time in milliseconds
                        $minutes = (int)$matches[4];
                        $seconds = (int)$matches[5];
                        $milliseconds = (int)$matches[6];
                        $metadata['time_ms'] = ($minutes * 60000) + ($seconds * 1000) + $milliseconds;

                        // Extract player name and country from (playername.Country) pattern
                        // Work backwards from ) to find the last . to split player and country
                        $playerAndCountry = $matches[7];
                        $lastDotPos = strrpos($playerAndCountry, '.');
                        if ($lastDotPos !== false) {
                            $metadata['player'] = substr($playerAndCountry, 0, $lastDotPos);
                            $metadata['country'] = substr($playerAndCountry, $lastDotPos + 1);
                        } else {
                            // No country found, just player name
                            $metadata['player'] = $playerAndCountry;
                            $metadata['country'] = null;
                        }
                    }
                }

                // Also extract direct values from XML
                if (isset($xml->game['mapname'])) {
                    $metadata['map'] = (string)$xml->game['mapname'];
                }
                if (isset($xml->player['df_name'])) {
                    $metadata['player'] = (string)$xml->player['df_name'];
                }
                if (isset($xml->record['bestTime'])) {
                    // Parse time format: 00.05.160
                    $timeStr = (string)$xml->record['bestTime'];
                    if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $timeStr, $timeMatch)) {
                        $minutes = (int)$timeMatch[1];
                        $seconds = (int)$timeMatch[2];
                        $milliseconds = (int)$timeMatch[3];
                        $metadata['time_ms'] = ($minutes * 60000) + ($seconds * 1000) + $milliseconds;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to parse DemoCleaner3 XML', ['error' => $e->getMessage()]);
            }
        }

        return $metadata;
    }

    /**
     * Compress demo file to save storage space
     * Supports: zip (PHP ZipArchive), 7z (p7zip command)
     */
    protected function compressDemo($tempFile, $processedFilename)
    {
        $format = config('app.demo_compression_format', '7z');
        $compressedFilename = pathinfo($processedFilename, PATHINFO_FILENAME) . '.' . $format;

        // Generate simple storage path for Backblaze
        $compressedPath = $this->generateStoragePath($compressedFilename, 'processed');

        // Note: With Backblaze B2, we don't need to create directories
        // Just upload the file directly - the path is just metadata

        // Create temporary compressed file
        $tempCompressedPath = storage_path('app/temp_' . uniqid() . '.' . $format);

        if ($format === '7z') {
            // Use 7z command with normal compression for good balance
            // -mx=5 = normal compression (good balance between speed and ratio)
            // -mmt=4 = use 4 threads for compression (parallelization)
            $escapedTemp = escapeshellarg($tempFile);
            $escapedOutput = escapeshellarg($tempCompressedPath);
            $escapedFilename = escapeshellarg($processedFilename);

            exec("7z a -t7z -mx=5 -mmt=4 $escapedOutput $escapedTemp 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Failed to create 7z archive: ' . implode("\n", $output));
            }

            // Rename the file inside the archive to match processed filename
            exec("7z rn $escapedOutput " . escapeshellarg(basename($tempFile)) . " $escapedFilename 2>&1");
        } else {
            // Fallback to ZIP using PHP's ZipArchive
            $zip = new \ZipArchive();
            if ($zip->open($tempCompressedPath, \ZipArchive::CREATE) !== TRUE) {
                throw new \Exception('Cannot create zip file for demo compression');
            }
            $zip->addFile($tempFile, $processedFilename);
            $zip->close();
        }

        // Return the local compressed file path (don't upload yet)
        // Upload will happen later only if record matching succeeds
        $originalSize = filesize($tempFile);
        $compressedSize = filesize($tempCompressedPath);

        Log::info('Demo compressed successfully', [
            'format' => $format,
            'original_file' => $processedFilename,
            'compressed_file' => $compressedFilename,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
        ]);

        return $tempCompressedPath;
    }

    /**
     * Upload compressed demo to Backblaze B2 storage
     */
    protected function uploadToBackblaze($localFilePath, $filename)
    {
        $storagePath = "demos/{$filename}";
        $fileContents = file_get_contents($localFilePath);

        try {
            $uploadSuccess = Storage::put($storagePath, $fileContents);

            if (!$uploadSuccess) {
                throw new \Exception('Storage::put() returned false - upload may have failed silently');
            }
        } catch (\Throwable $e) {
            Log::error('Failed to upload demo to Backblaze', [
                'path' => $storagePath,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw new \Exception('Failed to upload compressed demo to Backblaze B2 storage: ' . $e->getMessage(), 0, $e);
        }

        Log::info('Demo uploaded to Backblaze', [
            'path' => $storagePath,
            'size' => strlen($fileContents),
        ]);

        return $storagePath;
    }

    /**
     * Generate simple storage path for Backblaze
     * With Backblaze object storage, we don't need hierarchical paths for performance
     * Structure: demos/{filename}
     */
    protected function generateStoragePath($filename, $type = 'processed')
    {
        // Simple path - Backblaze handles millions of files without performance issues
        return "demos/{$filename}";
    }

    /**
     * Move failed demo to failed directory for admin review
     */
    protected function moveToFailedDirectory(UploadedDemo $demo, $compressedLocalPath = null)
    {
        try {
            $failedDir = storage_path("app/demos/failed/{$demo->id}");

            // Create failed directory
            if (!is_dir($failedDir)) {
                mkdir($failedDir, 0755, true);
            }

            // Use the compressed local path if provided, otherwise try the stored file_path
            $sourcePath = $compressedLocalPath ?? storage_path("app/{$demo->file_path}");

            // Move file to failed directory
            if (file_exists($sourcePath)) {
                $destPath = $failedDir . '/' . ($demo->processed_filename ?? $demo->original_filename);
                rename($sourcePath, $destPath);

                // Update file_path to point to failed directory (local path, not Backblaze)
                $demo->update(['file_path' => "demos/failed/{$demo->id}/" . ($demo->processed_filename ?? $demo->original_filename)]);

                Log::info('Moved failed demo to failed directory', [
                    'demo_id' => $demo->id,
                    'from' => $sourcePath,
                    'to' => $destPath,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to move demo to failed directory', [
                'demo_id' => $demo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Try to automatically assign demo to a record using name matching
     */
    protected function autoAssignToRecord(UploadedDemo $demo, $compressedLocalPath)
    {
        if (!$demo->map_name || !$demo->physics || !$demo->time_ms || !$demo->player_name) {
            return; // Not enough data to match
        }

        // IMPORTANT: Only assign ONLINE demos to records
        // Offline demos (df, fs, fc) should NOT be assigned to online records
        // They will have their own offline leaderboards
        if ($demo->gametype && !str_starts_with($demo->gametype, 'm')) {
            Log::info('Skipping auto-assign for offline demo', [
                'demo_id' => $demo->id,
                'gametype' => $demo->gametype,
                'map' => $demo->map_name,
            ]);
            return;
        }

        // Build gametype string (e.g., "run_cpm" or "run_vq3")
        // Records from q3df.org are all online records
        // Strip .tr suffix (timer reset) as it's just an indicator and not part of physics matching
        $physics = str_replace('.tr', '', strtolower($demo->physics));
        $gametype = 'run_' . $physics;

        // PASS 1: Check if uploader has a matching record (ignores name completely)
        $uploaderRecordMatch = false;
        if ($demo->user_id) {
            $uploaderRecord = Record::where('mapname', $demo->map_name)
                ->where('gametype', $gametype)
                ->where('time', $demo->time_ms)
                ->where('user_id', $demo->user_id)
                ->first();

            if ($uploaderRecord) {
                // Uploader has a matching record - assign immediately with 100% confidence
                $uploadedPath = $this->uploadToBackblaze($compressedLocalPath, $demo->processed_filename);

                $demo->update([
                    'record_id' => $uploaderRecord->id,
                    'status' => 'assigned',
                    'file_path' => $uploadedPath,
                    'name_confidence' => 100,
                    'suggested_user_id' => $demo->user_id,
                    'matched_alias' => null, // Matched by uploader record, not name
                ]);

                // Clean up local compressed file after successful upload
                if (file_exists($compressedLocalPath)) {
                    unlink($compressedLocalPath);
                }

                Log::info('Demo auto-assigned to uploader\'s record', [
                    'demo_id' => $demo->id,
                    'record_id' => $uploaderRecord->id,
                    'user_id' => $demo->user_id,
                    'gametype' => $gametype,
                    'uploaded_path' => $uploadedPath,
                ]);

                $uploaderRecordMatch = true;
                return;
            }
        }

        // PASS 2: Global name matching (only if uploader didn't match in PASS 1)
        if (!$uploaderRecordMatch) {
            $nameMatcher = app(NameMatcher::class);
            $nameMatch = $nameMatcher->findBestMatch($demo->player_name, null); // null = global search

            // Store name matching results including matched_alias
            $demo->update([
                'name_confidence' => $nameMatch['confidence'],
                'suggested_user_id' => $nameMatch['user_id'],
                'matched_alias' => $nameMatch['matched_name'] ?? null,
            ]);

            Log::info('Name matching completed', [
                'demo_id' => $demo->id,
                'player_name' => $demo->player_name,
                'confidence' => $nameMatch['confidence'],
                'suggested_user_id' => $nameMatch['user_id'],
                'matched_alias' => $nameMatch['matched_name'] ?? null,
                'source' => $nameMatch['source'],
            ]);

            // Only auto-assign if we have 100% confidence match
            if ($nameMatch['confidence'] === 100 && $nameMatch['user_id']) {
                // Find matching record for this user
                $record = Record::where('mapname', $demo->map_name)
                    ->where('gametype', $gametype)
                    ->where('time', $demo->time_ms)
                    ->where('user_id', $nameMatch['user_id'])
                    ->first();

                if ($record) {
                    // Perfect match found! Upload to Backblaze
                    $uploadedPath = $this->uploadToBackblaze($compressedLocalPath, $demo->processed_filename);

                    $demo->update([
                        'record_id' => $record->id,
                        'status' => 'assigned',
                        'file_path' => $uploadedPath,
                    ]);

                    // Clean up local compressed file after successful upload
                    if (file_exists($compressedLocalPath)) {
                        unlink($compressedLocalPath);
                    }

                    Log::info('Demo auto-assigned to record with 100% name match', [
                        'demo_id' => $demo->id,
                        'record_id' => $record->id,
                        'user_id' => $nameMatch['user_id'],
                        'gametype' => $demo->gametype,
                        'matched_alias' => $nameMatch['matched_name'] ?? null,
                        'uploaded_path' => $uploadedPath,
                    ]);

                    return;
                }
            }
        }

        // Less than 100% confidence or no matching record found
        // Create offline record instead (goes to "Demos Top")
        Log::info('Creating offline record for non-100% match', [
            'demo_id' => $demo->id,
            'confidence' => $nameMatch['confidence'],
            'map' => $demo->map_name,
            'time_ms' => $demo->time_ms,
        ]);

        $this->createOfflineRecord($demo, $compressedLocalPath);
    }

    /**
     * Create offline record from demo
     * Offline demos (df, fs, fc) get their own leaderboards separate from online records
     */
    protected function createOfflineRecord(UploadedDemo $demo, $compressedLocalPath, bool $useValidityAsFlag = false)
    {
        if (!$demo->map_name || !$demo->physics || !$demo->gametype || !$demo->time_ms) {
            Log::warning('Cannot create offline record - missing required fields', [
                'demo_id' => $demo->id,
                'map_name' => $demo->map_name,
                'physics' => $demo->physics,
                'gametype' => $demo->gametype,
                'time_ms' => $demo->time_ms,
            ]);
            return;
        }

        // Determine validity flag if this is a failed-validity demo
        $validityFlag = null;
        if ($useValidityAsFlag && $demo->validity) {
            // Parse validity JSON to get the first flag
            $validity = is_string($demo->validity) ? json_decode($demo->validity, true) : $demo->validity;
            if (!empty($validity)) {
                // Get the first validity issue as the flag (e.g., "client_finish=false")
                $firstKey = array_key_first($validity);
                $validityFlag = "{$firstKey}={$validity[$firstKey]}";
            }
        }

        // Calculate rank by counting how many faster times exist for this map/physics/gametype
        // For validity demos, also filter by validity_flag to create separate leaderboards
        $query = OfflineRecord::where('map_name', $demo->map_name)
            ->where('physics', $demo->physics)
            ->where('gametype', $demo->gametype)
            ->where('time_ms', '<', $demo->time_ms);

        if ($validityFlag) {
            $query->where('validity_flag', $validityFlag);
        } else {
            $query->whereNull('validity_flag');
        }

        $fasterTimes = $query->count();
        $rank = $fasterTimes + 1;

        // Upload to Backblaze for offline records too
        $uploadedPath = $this->uploadToBackblaze($compressedLocalPath, $demo->processed_filename);

        // Create the offline record using firstOrCreate to handle race conditions
        // Multiple workers might try to create the same offline record simultaneously
        // Use record_date from demo metadata if available, otherwise fall back to upload date
        try {
            $offlineRecord = OfflineRecord::firstOrCreate(
                ['demo_id' => $demo->id],
                [
                    'map_name' => $demo->map_name,
                    'physics' => $demo->physics,
                    'gametype' => $demo->gametype,
                    'validity_flag' => $validityFlag,
                    'time_ms' => $demo->time_ms,
                    'player_name' => $demo->player_name,
                    'rank' => $rank,
                    'date_set' => $demo->record_date ?? $demo->created_at,
                ]
            );

            Log::info('Offline record created or found for demo', [
                'demo_id' => $demo->id,
                'record_id' => $offlineRecord->id,
                'was_existing' => $offlineRecord->wasRecentlyCreated ? 'no' : 'yes',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create offline record', [
                'demo_id' => $demo->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Determine status based on whether this is an online demo (rematchable) or offline demo (final)
        // Online demos (mdf/mfs/mfc) that create offline_record should use 'fallback-assigned' (can be rematched later)
        // Offline demos (df/fs/fc) should use 'assigned' (final, won't be rematched)
        // IMPORTANT: Don't change status if it's already 'failed-validity' - keep that status!
        if ($demo->status !== 'failed-validity') {
            $status = ($demo->gametype && str_starts_with($demo->gametype, 'm')) ? 'fallback-assigned' : 'assigned';
        } else {
            // Keep failed-validity status for demos with validity issues
            $status = 'failed-validity';
        }

        // Update demo with uploaded path and appropriate status
        $demo->update([
            'file_path' => $uploadedPath,
            'status' => $status,
        ]);

        // Clean up local compressed file after successful upload
        if (file_exists($compressedLocalPath)) {
            unlink($compressedLocalPath);
        }

        // Update ranks for all records slower than this one
        // (increment their rank by 1 since a new faster/equal record was inserted)
        Log::info('DEBUG: About to update ranks', [
            'demo_id' => $demo->id,
            'map_name' => $demo->map_name,
            'physics' => $demo->physics,
            'demo->gametype (ORIGINAL)' => $demo->gametype,
            'validityFlag' => $validityFlag,
            'useValidityAsFlag' => $useValidityAsFlag,
            'offlineRecord->gametype' => $offlineRecord->gametype,
            'offlineRecord->validity_flag' => $offlineRecord->validity_flag,
        ]);

        $rankQuery = OfflineRecord::where('map_name', $demo->map_name)
            ->where('physics', $demo->physics)
            ->where('gametype', $demo->gametype)
            ->where('time_ms', '>=', $demo->time_ms)
            ->where('id', '!=', $offlineRecord->id);

        if ($validityFlag) {
            $rankQuery->where('validity_flag', $validityFlag);
        } else {
            $rankQuery->whereNull('validity_flag');
        }

        $rankQuery->increment('rank');

        Log::info('DEBUG: Ranks updated');

        Log::info('Offline record created and uploaded', [
            'demo_id' => $demo->id,
            'record_id' => $offlineRecord->id,
            'map' => $demo->map_name,
            'gametype' => $demo->gametype,
            'physics' => $demo->physics,
            'rank' => $rank,
            'time_ms' => $demo->time_ms,
            'status' => $status,
            'uploaded_path' => $uploadedPath,
        ]);
    }
}