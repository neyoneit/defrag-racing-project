<?php

namespace App\Services;

use App\Models\UploadedDemo;
use App\Models\Record;
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

            // Compress the demo file to save space
            $compressedPath = $this->compressDemo($tempFile, $processedFilename);

            // Update demo record
            $demo->update([
                'processed_filename' => basename($compressedPath),
                'file_path' => $compressedPath,
                'map_name' => $metadata['map'] ?? null,
                'physics' => $metadata['physics'] ?? null,
                'time_ms' => $metadata['time_ms'] ?? null,
                'player_name' => $metadata['player'] ?? null,
                'processing_output' => $output,
                'status' => 'processed',
            ]);

            // Clean up temp files
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);

            // Try to auto-assign to record
            $this->autoAssignToRecord($demo);

            return $demo;

        } catch (\Exception $e) {
            Log::error('Demo processing failed: ' . $e->getMessage(), [
                'demo_id' => $demo->id,
                'error' => $e->getMessage(),
            ]);

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
        // Use new Python implementation
        $processSingleScript = dirname($this->batchRenamerPath) . '/process_single_demo.py';

        // Run the Python script (suppress warnings to stderr)
        $command = 'cd ' . escapeshellarg(dirname($this->batchRenamerPath)) . ' && ';
        $command .= 'python3 -W ignore ' . escapeshellarg($processSingleScript) . ' ' . escapeshellarg($filepath) . ' 2>/dev/null';

        $output = shell_exec($command);

        if ($output === null) {
            throw new \Exception('Failed to execute Python demo processor');
        }

        // The new implementation returns just the suggested filename
        // We need to create a fake XML structure for compatibility with existing parsing
        $suggestedFilename = trim($output);
        if (!empty($suggestedFilename) && !str_contains($suggestedFilename, 'Error:')) {
            // Create a simple XML structure with the suggested filename
            $xml = '<?xml version="1.0"?><demoFile><fileName suggestedFileName="' . htmlspecialchars($suggestedFilename) . '"/></demoFile>';
            return $xml;
        } else {
            throw new \Exception('Demo processing failed: ' . $output);
        }
    }

    /**
     * Parse BatchDemoRenamer output to extract metadata
     * Format expected: XML output from DemoCleaner3
     */
    protected function parseRenamerOutput($output)
    {
        $metadata = [];

        // Extract XML from the output (skip non-XML lines like GTK warnings)
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
     */
    protected function compressDemo($tempFile, $processedFilename)
    {
        $compressedFilename = pathinfo($processedFilename, PATHINFO_FILENAME) . '.zip';

        // Create hierarchical directory structure for better performance
        $compressedPath = $this->generateStoragePath($compressedFilename, 'processed');

        // Ensure directory exists using Laravel Storage facade
        $directory = dirname($compressedPath);
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        // Create a temporary zip file
        $tempZipPath = storage_path('app/temp_' . uniqid() . '.zip');

        $zip = new \ZipArchive();
        if ($zip->open($tempZipPath, \ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create zip file for demo compression');
        }

        // Add the demo file to the zip
        $zip->addFile($tempFile, $processedFilename);
        $zip->close();

        // Store the compressed file
        Storage::put($compressedPath, file_get_contents($tempZipPath));

        // Clean up temporary zip file
        unlink($tempZipPath);

        Log::info('Demo compressed successfully', [
            'original_file' => $processedFilename,
            'compressed_file' => $compressedFilename,
            'original_size' => filesize($tempFile),
            'compressed_size' => Storage::size($compressedPath)
        ]);

        return $compressedPath;
    }

    /**
     * Generate hierarchical storage path for better filesystem performance
     * Structure: demos/{type}/{year}/{month}/{day}/{hash_prefix}/{filename}
     * This keeps directories under ~1000 files each for optimal performance
     */
    protected function generateStoragePath($filename, $type = 'processed')
    {
        $date = now();
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        // Use filename hash to distribute files evenly across subdirectories
        // Take first 2 characters of MD5 hash for 256 possible subdirectories
        $hashPrefix = substr(md5($filename), 0, 2);

        // Create path: demos/processed/2024/03/15/a7/filename.zip
        return "demos/{$type}/{$year}/{$month}/{$day}/{$hashPrefix}/{$filename}";
    }

    /**
     * Try to automatically assign demo to a record
     */
    protected function autoAssignToRecord(UploadedDemo $demo)
    {
        if (!$demo->map_name || !$demo->physics || !$demo->time_ms) {
            return; // Not enough data to match
        }

        // Build gametype string (e.g., "run_cpm" or "run_vq3")
        $gametype = 'run_' . strtolower($demo->physics);

        // Find matching record
        $record = Record::where('mapname', $demo->map_name)
            ->where('gametype', $gametype)
            ->where('time', $demo->time_ms)
            ->where('user_id', $demo->user_id)
            ->first();

        if ($record) {
            $demo->update([
                'record_id' => $record->id,
                'status' => 'assigned',
            ]);

            Log::info('Demo auto-assigned to record', [
                'demo_id' => $demo->id,
                'record_id' => $record->id,
            ]);
        }
    }
}