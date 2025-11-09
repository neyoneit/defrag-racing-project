<?php

namespace App\Jobs;

use App\Models\UploadedDemo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExtractAndQueueArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes for large archives
    public $tries = 2;
    public $backoff = [30, 60];

    protected string $archivePath;
    protected int $userId;
    protected string $originalFilename;

    /**
     * Create a new job instance.
     */
    public function __construct(string $archivePath, int $userId, string $originalFilename)
    {
        $this->archivePath = $archivePath;
        $this->userId = $userId;
        $this->originalFilename = $originalFilename;
        $this->onQueue('demos'); // Use same queue as demo processing
    }

    /**
     * Execute the job - extract archive and queue individual demos
     */
    public function handle(): void
    {
        Log::info('Extracting archive for user', [
            'user_id' => $this->userId,
            'archive' => $this->originalFilename,
        ]);

        try {
            $extractedDemos = $this->extractArchive($this->archivePath);

            Log::info('Archive extracted, queueing demos', [
                'user_id' => $this->userId,
                'archive' => $this->originalFilename,
                'demo_count' => count($extractedDemos),
            ]);

            // Create UploadedDemo records and queue ProcessDemoJob for each
            foreach ($extractedDemos as $demoFile) {
                $fileHash = md5_file($demoFile['path']);

                // Check for duplicates
                $existingDemo = UploadedDemo::where('file_hash', $fileHash)->first();
                if ($existingDemo) {
                    @unlink($demoFile['path']);
                    continue;
                }

                // Check for user filename duplicates
                $existingByFilename = UploadedDemo::where('user_id', $this->userId)
                    ->where('original_filename', $demoFile['name'])
                    ->first();

                if ($existingByFilename) {
                    @unlink($demoFile['path']);
                    continue;
                }

                // Create database record
                $demo = UploadedDemo::create([
                    'original_filename' => $demoFile['name'],
                    'file_path' => '', // Will be set during processing
                    'file_size' => $demoFile['size'],
                    'file_hash' => $fileHash,
                    'user_id' => $this->userId,
                    'status' => 'uploaded',
                ]);

                // Store file locally in temp directory
                $directory = storage_path("app/demos/temp/{$demo->id}");
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                $destPath = "{$directory}/{$demoFile['name']}";
                rename($demoFile['path'], $destPath);

                // Queue for processing
                ProcessDemoJob::dispatch($demo);
            }

            // Clean up archive file
            @unlink($this->archivePath);

        } catch (\Exception $e) {
            Log::error('Archive extraction failed', [
                'user_id' => $this->userId,
                'archive' => $this->originalFilename,
                'error' => $e->getMessage(),
            ]);

            // Clean up archive file
            @unlink($this->archivePath);

            throw $e;
        }
    }

    /**
     * Extract demo files from archive
     * Returns array of ['name' => ..., 'path' => ..., 'size' => ...]
     */
    protected function extractArchive(string $archivePath): array
    {
        $extractedDemos = [];
        $tempExtractDir = storage_path('app/temp_extract_' . uniqid());
        mkdir($tempExtractDir, 0755, true);

        $extension = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));

        try {
            if ($extension === 'zip') {
                $extractedDemos = $this->extractZip($archivePath, $tempExtractDir);
            } elseif (in_array($extension, ['rar', '7z'])) {
                $extractedDemos = $this->extract7z($archivePath, $tempExtractDir);
            } else {
                throw new \Exception("Unsupported archive format: {$extension}");
            }
        } finally {
            // Clean up temp extraction directory
            if (file_exists($tempExtractDir)) {
                $this->removeDirectory($tempExtractDir);
            }
        }

        return $extractedDemos;
    }

    /**
     * Extract ZIP archive
     */
    protected function extractZip(string $archivePath, string $extractDir): array
    {
        $zip = new \ZipArchive();
        $extractedDemos = [];

        if ($zip->open($archivePath) !== true) {
            throw new \Exception('Failed to open ZIP archive');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Only extract .dm_* files
            if (!preg_match('/\.dm_[0-9]{2,3}$/i', $filename)) {
                continue;
            }

            $basename = basename($filename);
            $tempPath = storage_path('app/temp_demo_' . uniqid() . '_' . $basename);

            // Extract file
            $fp = $zip->getStream($filename);
            if ($fp) {
                file_put_contents($tempPath, $fp);
                fclose($fp);

                $extractedDemos[] = [
                    'name' => $basename,
                    'path' => $tempPath,
                    'size' => filesize($tempPath),
                ];
            }
        }

        $zip->close();
        return $extractedDemos;
    }

    /**
     * Extract RAR/7z archive using 7z binary
     */
    protected function extract7z(string $archivePath, string $extractDir): array
    {
        $extractedDemos = [];
        $escapedArchive = escapeshellarg($archivePath);
        $escapedExtractDir = escapeshellarg($extractDir);

        // Extract all files
        exec("7z x -o{$escapedExtractDir} {$escapedArchive} 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to extract archive with 7z: ' . implode("\n", $output));
        }

        // Find all .dm_* files recursively
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.dm_[0-9]{2,3}$/i', $file->getFilename())) {
                $basename = $file->getFilename();
                $tempPath = storage_path('app/temp_demo_' . uniqid() . '_' . $basename);

                // Move file to temp location
                rename($file->getPathname(), $tempPath);

                $extractedDemos[] = [
                    'name' => $basename,
                    'path' => $tempPath,
                    'size' => filesize($tempPath),
                ];
            }
        }

        return $extractedDemos;
    }

    /**
     * Recursively remove a directory
     */
    protected function removeDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
