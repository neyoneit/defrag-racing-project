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

            // Compress the demo file to save space and upload to Backblaze
            $compressedPath = $this->compressDemo($tempFile, $processedFilename);

            // Update demo record
            $demo->update([
                'processed_filename' => basename($compressedPath),
                'file_path' => $compressedPath,
                'map_name' => $metadata['map'] ?? null,
                'physics' => $metadata['physics'] ?? null,
                'gametype' => $metadata['gametype'] ?? null,
                'time_ms' => $metadata['time_ms'] ?? null,
                'player_name' => $metadata['player'] ?? null,
                'record_date' => $metadata['record_date'] ?? null,
                'processing_output' => $output,
                'status' => 'processed',
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

            if ($demo->is_offline) {
                // Create offline record for offline demos
                $this->createOfflineRecord($demo);
            } else {
                // Auto-assign online demos to existing records
                $this->autoAssignToRecord($demo);
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

            // Parse the suggested filename to extract components
            $suggestedName = $jsonData['suggested_filename'];
            if (preg_match('/([^[]+)\[([^.]+)\.([^\]]+)\](\d+)\.(\d+)\.(\d+)\(([^)]+)\)\.dm_\d+/', $suggestedName, $matches)) {
                $metadata['map'] = $matches[1];
                $metadata['gametype'] = $matches[2]; // mdf, df, etc.
                $metadata['physics'] = strtoupper($matches[3]); // VQ3 or CPM

                // Calculate time in milliseconds
                $minutes = (int)$matches[4];
                $seconds = (int)$matches[5];
                $milliseconds = (int)$matches[6];
                $metadata['time_ms'] = ($minutes * 60000) + ($seconds * 1000) + $milliseconds;

                $metadata['player'] = $matches[7];
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

                        $metadata['player'] = $matches[7];
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
            // Use 7z command for better compression ratio
            // -mx=5 = normal compression (good balance of speed/ratio)
            // -mmt=2 = use 2 threads for compression
            $escapedTemp = escapeshellarg($tempFile);
            $escapedOutput = escapeshellarg($tempCompressedPath);
            $escapedFilename = escapeshellarg($processedFilename);

            exec("7z a -t7z -mx=5 -mmt=2 $escapedOutput $escapedTemp 2>&1", $output, $returnCode);

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

        // Store the compressed file to Backblaze
        $fileContents = file_get_contents($tempCompressedPath);
        $uploadSuccess = Storage::put($compressedPath, $fileContents);

        if (!$uploadSuccess) {
            throw new \Exception('Failed to upload compressed demo to Backblaze B2 storage');
        }

        Log::info('Demo uploaded to Backblaze', [
            'path' => $compressedPath,
            'size' => strlen($fileContents),
        ]);

        // Clean up temporary file
        unlink($tempCompressedPath);

        // Attempt to get compressed size via Storage
        $compressedSize = null;
        try {
            $compressedSize = Storage::size($compressedPath);
        } catch (\Throwable $e) {
            Log::warning('Unable to retrieve compressed file size via Storage::size(), falling back', [
                'compressed_path' => $compressedPath,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Demo compressed successfully', [
            'format' => $format,
            'original_file' => $processedFilename,
            'compressed_file' => $compressedFilename,
            'original_size' => filesize($tempFile),
            'compressed_size' => $compressedSize,
        ]);

        return $compressedPath;
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
    protected function moveToFailedDirectory(UploadedDemo $demo)
    {
        try {
            $sourcePath = storage_path("app/{$demo->file_path}");
            $failedDir = storage_path("app/demos/failed/{$demo->id}");

            // Create failed directory
            if (!is_dir($failedDir)) {
                mkdir($failedDir, 0755, true);
            }

            // Move file to failed directory
            if (file_exists($sourcePath)) {
                $destPath = $failedDir . '/' . $demo->original_filename;
                rename($sourcePath, $destPath);

                // Update file_path to point to failed directory
                $demo->update(['file_path' => "demos/failed/{$demo->id}/{$demo->original_filename}"]);

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
     * Try to automatically assign demo to a record
     */
    protected function autoAssignToRecord(UploadedDemo $demo)
    {
        if (!$demo->map_name || !$demo->physics || !$demo->time_ms) {
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
        $gametype = 'run_' . strtolower($demo->physics);

        // Find matching record
        $query = Record::where('mapname', $demo->map_name)
            ->where('gametype', $gametype)
            ->where('time', $demo->time_ms);

        // If the demo was uploaded by a logged-in user, prefer records for that user.
        // For guest uploads (user_id == null) allow matching records regardless of owner so
        // community-submitted demos can be auto-assigned to existing public records.
        if (!is_null($demo->user_id)) {
            $query->where('user_id', $demo->user_id);
        } else {
            Log::info('Auto-assign: demo uploaded by guest, searching records without user constraint', [
                'demo_id' => $demo->id,
                'map' => $demo->map_name,
                'time_ms' => $demo->time_ms,
            ]);
        }

        $record = $query->first();

        if ($record) {
            $demo->update([
                'record_id' => $record->id,
                'status' => 'assigned',
            ]);

            Log::info('Demo auto-assigned to record', [
                'demo_id' => $demo->id,
                'record_id' => $record->id,
                'gametype' => $demo->gametype,
            ]);
        } else {
            // Online demo with no matching record should be marked as failed
            $demo->update([
                'status' => 'failed',
            ]);

            Log::warning('Online demo failed to match any record', [
                'demo_id' => $demo->id,
                'map' => $demo->map_name,
                'gametype' => $gametype,
                'time_ms' => $demo->time_ms,
                'user_id' => $demo->user_id,
            ]);

            // Move the failed demo file to failed directory for review
            $this->moveToFailedDirectory($demo);
        }
    }

    /**
     * Create offline record from demo
     * Offline demos (df, fs, fc) get their own leaderboards separate from online records
     */
    protected function createOfflineRecord(UploadedDemo $demo)
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

        // Check if offline record already exists for this demo
        $existingRecord = OfflineRecord::where('demo_id', $demo->id)->first();
        if ($existingRecord) {
            Log::info('Offline record already exists for demo', [
                'demo_id' => $demo->id,
                'record_id' => $existingRecord->id,
            ]);
            return;
        }

        // Calculate rank by counting how many faster times exist for this map/physics/gametype
        $fasterTimes = OfflineRecord::where('map_name', $demo->map_name)
            ->where('physics', $demo->physics)
            ->where('gametype', $demo->gametype)
            ->where('time_ms', '<', $demo->time_ms)
            ->count();

        $rank = $fasterTimes + 1;

        // Create the offline record
        // Use record_date from demo metadata if available, otherwise fall back to upload date
        $offlineRecord = OfflineRecord::create([
            'map_name' => $demo->map_name,
            'physics' => $demo->physics,
            'gametype' => $demo->gametype,
            'time_ms' => $demo->time_ms,
            'player_name' => $demo->player_name,
            'demo_id' => $demo->id,
            'rank' => $rank,
            'date_set' => $demo->record_date ?? $demo->created_at,
        ]);

        // Update ranks for all records slower than this one
        // (increment their rank by 1 since a new faster/equal record was inserted)
        OfflineRecord::where('map_name', $demo->map_name)
            ->where('physics', $demo->physics)
            ->where('gametype', $demo->gametype)
            ->where('time_ms', '>=', $demo->time_ms)
            ->where('id', '!=', $offlineRecord->id)
            ->increment('rank');

        Log::info('Offline record created', [
            'demo_id' => $demo->id,
            'record_id' => $offlineRecord->id,
            'map' => $demo->map_name,
            'gametype' => $demo->gametype,
            'physics' => $demo->physics,
            'rank' => $rank,
            'time_ms' => $demo->time_ms,
        ]);
    }
}