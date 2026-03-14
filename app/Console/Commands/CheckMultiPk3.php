<?php

namespace App\Console\Commands;

use App\Models\PlayerModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CheckMultiPk3 extends Command
{
    protected $signature = 'models:check-downloads
        {--fix : Actually download and import missing/mismatched PK3s (without this flag, only reports)}
        {--file= : Process only a specific filename from download history (e.g., boots.zip)}
        {--limit=0 : Limit number of files to check (0 = all)}
        {--user-id=8 : User ID to assign new models to}
        {--sleep=2 : Seconds to wait between downloads}';

    protected $description = 'Audit all downloaded models: compare stored PK3s against ws.q3df.org originals via MD5, detect missing/mismatched files';

    private $scraper;

    public function handle()
    {
        $historyPath = 'bulk-upload/downloaded_history.json';

        if (!Storage::disk('local')->exists($historyPath)) {
            $this->error('Download history not found: ' . $historyPath);
            return 1;
        }

        $history = json_decode(Storage::disk('local')->get($historyPath), true) ?? [];
        $isFix = $this->option('fix');
        $fileFilter = $this->option('file');
        $limit = (int) $this->option('limit');
        $sleepSeconds = (int) $this->option('sleep');

        // Filter if specific file requested
        if ($fileFilter) {
            $history = array_filter($history, function ($key) use ($fileFilter) {
                return strtolower($key) === strtolower($fileFilter);
            }, ARRAY_FILTER_USE_KEY);
        }

        $totalEntries = count($history);
        $this->info("Auditing {$totalEntries} files from download history...");
        $this->newLine();

        $stats = [
            'ok' => 0,
            'mismatch' => 0,
            'missing' => 0,
            'multi_pk3' => 0,
            'download_error' => 0,
            'fixed' => 0,
        ];

        $problems = []; // Collect all problems for summary
        $allResults = []; // Full audit log for JSON export
        $checked = 0;

        foreach ($history as $filename => $entry) {
            if ($limit > 0 && $checked >= $limit) break;
            $checked++;

            $isZip = str_ends_with(strtolower($filename), '.zip');
            $isPk3 = str_ends_with(strtolower($filename), '.pk3');

            if (!$isZip && !$isPk3) {
                $this->warn("[{$checked}/{$totalEntries}] {$filename} - unknown extension, skipping");
                continue;
            }

            $this->line("[{$checked}/{$totalEntries}] {$filename}...");

            try {
                $url = 'https://ws.q3df.org/models/downloads/' . $filename;
                $response = Http::withOptions([
                    'verify' => false,
                    'timeout' => 180,
                ])->get($url);

                if (!$response->successful()) {
                    $this->warn("  Download failed (HTTP {$response->status()})");
                    $stats['download_error']++;
                    continue;
                }

                $body = $response->body();

                if (strlen($body) < 4 || substr($body, 0, 2) !== 'PK') {
                    $this->warn("  Not a valid PK3/ZIP file");
                    $stats['download_error']++;
                    continue;
                }

                $tempFile = storage_path('app/models/temp/audit-' . uniqid() . '.tmp');
                if (!file_exists(dirname($tempFile))) {
                    mkdir(dirname($tempFile), 0755, true);
                }
                file_put_contents($tempFile, $body);

                if ($isPk3) {
                    $this->auditDirectPk3($filename, $tempFile, $entry, $stats, $problems, $allResults, $isFix);
                } else {
                    $this->auditZip($filename, $tempFile, $entry, $stats, $problems, $allResults, $isFix);
                }

                @unlink($tempFile);

                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }

            } catch (\Exception $e) {
                $this->error("  Error: " . $e->getMessage());
                $stats['download_error']++;
                continue;
            }
        }

        // Save results to JSON
        $reportPath = 'audit-results-' . date('Y-m-d_H-i-s') . '.json';
        $report = [
            'generated_at' => now()->toIso8601String(),
            'stats' => $stats,
            'problems' => $problems,
            'results' => $allResults,
        ];
        Storage::disk('local')->put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info("Report saved to: storage/app/{$reportPath}");

        // Summary
        $this->newLine();
        $this->info("╔══════════════════════════════════════╗");
        $this->info("║          AUDIT SUMMARY               ║");
        $this->info("╠══════════════════════════════════════╣");
        $this->info("║  Files checked:     {$checked}");
        $this->info("║  OK (MD5 match):    {$stats['ok']}");
        $this->warn("║  MISMATCH:          {$stats['mismatch']}");
        $this->error("║  MISSING:           {$stats['missing']}");
        $this->info("║  Multi-PK3 ZIPs:    {$stats['multi_pk3']}");
        $this->info("║  Download errors:   {$stats['download_error']}");
        if ($isFix) {
            $this->info("║  Fixed:             {$stats['fixed']}");
        }
        $this->info("╚══════════════════════════════════════╝");

        if (!empty($problems)) {
            $this->newLine();
            $this->info("=== PROBLEMS ===");
            foreach ($problems as $problem) {
                $color = $problem['type'] === 'MISSING' ? 'error' : 'warn';
                $this->$color("[{$problem['type']}] {$problem['file']} -> {$problem['detail']}");
            }
        }

        return 0;
    }

    /**
     * Audit a direct PK3 file (not inside a ZIP)
     */
    private function auditDirectPk3(string $filename, string $tempFile, array $entry, array &$stats, array &$problems, array &$allResults, bool $isFix): void
    {
        $remoteMd5 = md5_file($tempFile);
        $remoteSize = filesize($tempFile);

        $storedPk3 = $this->findStoredPk3ForFile($filename, $entry);

        if (!$storedPk3) {
            $stats['missing']++;
            $problems[] = ['type' => 'MISSING', 'file' => $filename, 'detail' => 'No stored PK3 found in DB'];
            $allResults[] = ['file' => $filename, 'status' => 'MISSING', 'remote_md5' => $remoteMd5, 'remote_size' => $remoteSize, 'detail' => 'No stored PK3 found in DB'];
            $this->error("  MISSING - no stored PK3 found");

            if ($isFix) {
                $fixed = $this->fixMissingPk3($tempFile, $filename, $entry);
                $stats['fixed'] += $fixed;
            }
            return;
        }

        $storedFullPath = storage_path('app/' . $storedPk3);
        if (!file_exists($storedFullPath)) {
            $stats['missing']++;
            $problems[] = ['type' => 'MISSING', 'file' => $filename, 'detail' => "DB has zip_path={$storedPk3} but file doesn't exist on disk"];
            $allResults[] = ['file' => $filename, 'status' => 'MISSING', 'remote_md5' => $remoteMd5, 'remote_size' => $remoteSize, 'stored_path' => $storedPk3, 'detail' => 'File missing on disk'];
            $this->error("  MISSING - DB record exists but file not on disk: {$storedPk3}");
            return;
        }

        $localMd5 = md5_file($storedFullPath);
        $localSize = filesize($storedFullPath);

        if ($remoteMd5 === $localMd5) {
            $stats['ok']++;
            $allResults[] = ['file' => $filename, 'status' => 'OK', 'md5' => $remoteMd5, 'size' => $remoteSize, 'stored_path' => $storedPk3];
            $this->info("  OK (MD5: {$remoteMd5})");
        } else {
            $stats['mismatch']++;
            $localSizeKB = round($localSize / 1024);
            $remoteSizeKB = round($remoteSize / 1024);
            $problems[] = [
                'type' => 'MISMATCH',
                'file' => $filename,
                'detail' => "local={$localMd5} ({$localSizeKB}KB) vs remote={$remoteMd5} ({$remoteSizeKB}KB)"
            ];
            $allResults[] = ['file' => $filename, 'status' => 'MISMATCH', 'local_md5' => $localMd5, 'local_size' => $localSize, 'remote_md5' => $remoteMd5, 'remote_size' => $remoteSize, 'stored_path' => $storedPk3];
            $this->warn("  MISMATCH - local: {$localMd5} ({$localSizeKB}KB) vs remote: {$remoteMd5} ({$remoteSizeKB}KB)");
        }
    }

    /**
     * Audit a ZIP file containing one or more PK3 files
     */
    private function auditZip(string $filename, string $tempFile, array $entry, array &$stats, array &$problems, array &$allResults, bool $isFix): void
    {
        $zip = new ZipArchive;
        if ($zip->open($tempFile) !== TRUE) {
            $this->warn("  Cannot open ZIP");
            $stats['download_error']++;
            return;
        }

        $pk3FileNames = [];
        $allZipContents = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $allZipContents[] = $name;
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pk3') {
                $pk3FileNames[] = $name;
            }
        }

        $this->line("  ZIP contents (" . count($allZipContents) . " files): " . implode(', ', $allZipContents));

        if (empty($pk3FileNames)) {
            $zip->close();
            $this->warn("  ZIP contains no PK3 files, treating as direct PK3");
            $this->auditDirectPk3($filename, $tempFile, $entry, $stats, $problems, $allResults, $isFix);
            return;
        }

        if (count($pk3FileNames) > 1) {
            $stats['multi_pk3']++;
            $this->info("  MULTI-PK3: " . count($pk3FileNames) . " PK3 files inside");
        }

        $tempExtract = storage_path('app/models/temp/audit-extract-' . uniqid());
        mkdir($tempExtract, 0755, true);
        $zip->extractTo($tempExtract);
        $zip->close();

        foreach ($pk3FileNames as $pk3FileName) {
            $pk3Path = $tempExtract . '/' . $pk3FileName;
            if (!file_exists($pk3Path)) continue;

            $remoteMd5 = md5_file($pk3Path);
            $remoteSize = filesize($pk3Path);
            $remoteSizeKB = round($remoteSize / 1024);

            $modelsInPk3 = $this->analyzeModelsInPk3($pk3Path);
            $modelsList = !empty($modelsInPk3['names'])
                ? implode(', ', $modelsInPk3['names']) . " ({$modelsInPk3['type']})"
                : 'no models detected';

            $storedPk3 = $this->findStoredPk3ByMd5($remoteMd5);

            if ($storedPk3) {
                $stats['ok']++;
                $allResults[] = ['file' => $filename, 'pk3' => $pk3FileName, 'status' => 'OK', 'md5' => $remoteMd5, 'size' => $remoteSize, 'models' => $modelsInPk3, 'stored_path' => $storedPk3];
                $this->info("    {$pk3FileName} ({$remoteSizeKB}KB): {$modelsList} - OK (matches {$storedPk3})");
                continue;
            }

            $allModelsExist = true;
            $missingModels = [];
            foreach ($modelsInPk3['names'] as $modelName) {
                if (!PlayerModel::whereRaw('LOWER(base_model) = ?', [strtolower($modelName)])->exists()) {
                    $allModelsExist = false;
                    $missingModels[] = $modelName;
                }
            }

            if ($allModelsExist && !empty($modelsInPk3['names'])) {
                $stats['mismatch']++;
                $problems[] = [
                    'type' => 'MISMATCH',
                    'file' => "{$filename} -> {$pk3FileName}",
                    'detail' => "Models exist in DB ({$modelsList}) but no stored PK3 matches MD5 {$remoteMd5}"
                ];
                $allResults[] = ['file' => $filename, 'pk3' => $pk3FileName, 'status' => 'MISMATCH', 'remote_md5' => $remoteMd5, 'remote_size' => $remoteSize, 'models' => $modelsInPk3, 'detail' => 'Models in DB but PK3 file differs'];
                $this->warn("    {$pk3FileName} ({$remoteSizeKB}KB): {$modelsList} - MISMATCH (models in DB but PK3 file differs)");
            } else {
                $stats['missing']++;
                $missingStr = !empty($missingModels) ? implode(', ', $missingModels) : 'all';
                $problems[] = [
                    'type' => 'MISSING',
                    'file' => "{$filename} -> {$pk3FileName}",
                    'detail' => "Missing models: {$missingStr} ({$modelsInPk3['type']}, {$remoteSizeKB}KB)"
                ];
                $allResults[] = ['file' => $filename, 'pk3' => $pk3FileName, 'status' => 'MISSING', 'remote_md5' => $remoteMd5, 'remote_size' => $remoteSize, 'models' => $modelsInPk3, 'missing_models' => $missingModels];
                $this->error("    {$pk3FileName} ({$remoteSizeKB}KB): {$modelsList} - MISSING ({$missingStr})");

                if ($isFix) {
                    $fixed = $this->fixMissingPk3FromZip($pk3Path, $pk3FileName, $filename, $entry);
                    $stats['fixed'] += $fixed;
                }
            }
        }

        $this->deleteDirectory($tempExtract);
    }

    /**
     * Analyze what models are inside a PK3 file
     */
    private function analyzeModelsInPk3(string $pk3Path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($pk3Path) !== TRUE) {
            return ['names' => [], 'type' => 'unknown'];
        }

        $models = [];
        $hasMd3 = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fname = strtolower($zip->getNameIndex($i));
            if (preg_match('#^models/players/([^/]+)/#', $fname, $m)) {
                $models[$m[1]] = true;
            }
            if (str_ends_with($fname, '.md3')) {
                $hasMd3 = true;
            }
        }
        $zip->close();

        return [
            'names' => array_keys($models),
            'type' => $hasMd3 ? 'complete' : 'skin-only',
        ];
    }

    /**
     * Find stored PK3 path for a given download history entry
     */
    private function findStoredPk3ForFile(string $filename, array $entry): ?string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        // Search DB for models from this file
        $model = PlayerModel::where('zip_path', 'LIKE', '%' . \Str::slug($baseName) . '%')
            ->whereNotNull('zip_path')
            ->first(['zip_path']);

        return $model?->zip_path;
    }

    /**
     * Find a stored PK3 file that matches a given MD5 hash
     */
    private function findStoredPk3ByMd5(string $md5): ?string
    {
        $pk3Dir = storage_path('app/models/pk3s');
        if (!is_dir($pk3Dir)) return null;

        // Get all unique zip_paths from DB to narrow the search
        static $allPk3Files = null;
        if ($allPk3Files === null) {
            $allPk3Files = PlayerModel::whereNotNull('zip_path')
                ->distinct()
                ->pluck('zip_path')
                ->toArray();
        }

        foreach ($allPk3Files as $pk3Path) {
            $fullPath = storage_path('app/' . $pk3Path);
            if (file_exists($fullPath) && md5_file($fullPath) === $md5) {
                return $pk3Path;
            }
        }

        return null;
    }

    /**
     * Fix a missing direct PK3 by importing it
     */
    private function fixMissingPk3(string $tempFile, string $filename, array $entry): int
    {
        $userId = (int) $this->option('user-id');
        $scraper = $this->getScraper();

        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $pk3Slug = \Str::slug($baseName) . '-' . time() . '-' . rand(1000, 9999);

        $extractPath = storage_path('app/public/models/extracted/' . $pk3Slug);
        mkdir($extractPath, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($tempFile) !== TRUE) return 0;
        $zip->extractTo($extractPath);
        $zip->close();

        $scraper->lowercaseFileExtensions($extractPath);
        $scraper->generateFileManifest($extractPath);

        $pk3StorePath = 'models/pk3s/' . $pk3Slug . '.pk3';
        copy($tempFile, storage_path('app/' . $pk3StorePath));

        $modelData = [
            'name' => $entry['model_name'] ?? ucfirst($baseName),
            'author' => $entry['author'] ?? 'Unknown',
            'pk3_file' => $filename,
            'pk3_url' => '',
        ];

        $created = $scraper->processExtractedPk3($extractPath, $pk3Slug, $pk3StorePath, $modelData, $userId);

        foreach ($created as $model) {
            $this->info("    Fixed: created {$model['name']}");
        }

        if (empty($created)) {
            $this->deleteDirectory($extractPath);
            @unlink(storage_path('app/' . $pk3StorePath));
            $this->line("    No new models created (all duplicates or empty)");
        }

        return count($created);
    }

    /**
     * Fix a missing PK3 from inside a ZIP
     */
    private function fixMissingPk3FromZip(string $pk3Path, string $pk3FileName, string $zipFilename, array $entry): int
    {
        $userId = (int) $this->option('user-id');
        $scraper = $this->getScraper();

        $zipBaseName = pathinfo($zipFilename, PATHINFO_FILENAME);
        $pk3BaseName = pathinfo(basename($pk3FileName), PATHINFO_FILENAME);
        $pk3Slug = \Str::slug($zipBaseName) . '-' . \Str::slug($pk3BaseName) . '-' . time() . '-' . rand(1000, 9999);

        $extractPath = storage_path('app/public/models/extracted/' . $pk3Slug);
        mkdir($extractPath, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($pk3Path) !== TRUE) return 0;
        $zip->extractTo($extractPath);
        $zip->close();

        $scraper->lowercaseFileExtensions($extractPath);
        $scraper->generateFileManifest($extractPath);

        $pk3StorePath = 'models/pk3s/' . $pk3Slug . '.pk3';
        copy($pk3Path, storage_path('app/' . $pk3StorePath));

        $modelData = [
            'name' => $entry['model_name'] ?? ucfirst($zipBaseName),
            'author' => $entry['author'] ?? 'Unknown',
            'pk3_file' => $zipFilename,
            'pk3_url' => '',
        ];

        $created = $scraper->processExtractedPk3($extractPath, $pk3Slug, $pk3StorePath, $modelData, $userId);

        foreach ($created as $model) {
            $this->info("    Fixed: created {$model['name']}");
        }

        if (empty($created)) {
            $this->deleteDirectory($extractPath);
            @unlink(storage_path('app/' . $pk3StorePath));
            $this->line("    No new models created (all duplicates or empty)");
        }

        return count($created);
    }

    private function getScraper(): ScrapeQ3dfModels
    {
        if (!$this->scraper) {
            $this->scraper = new ScrapeQ3dfModels();
            $this->scraper->setLaravel($this->getLaravel());
        }
        return $this->scraper;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) return;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath());
        }

        rmdir($dir);
    }
}
