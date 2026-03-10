<?php

namespace App\Http\Controllers;

use App\Console\Commands\ScrapeQ3dfModels;
use App\Models\PlayerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use ZipArchive;

class ModelsAuditController extends Controller
{
    private ?string $currentDownloadFile = null;

    public function index()
    {
        $report = $this->loadLatestReport();

        return Inertia::render('Admin/ModelsAudit', [
            'report' => $report,
        ]);
    }

    /**
     * Download a file from ws.q3df.org, inspect its contents (recursively for nested ZIPs/PK3s),
     * compare with local file if available, return detailed comparison.
     */
    public function download(Request $request)
    {
        $downloadUrl = $request->query('download_url');
        $downloadFile = $request->query('download_file');

        if (!$downloadUrl || !$downloadFile) {
            return response()->json(['error' => 'Missing parameters'], 422);
        }

        $wsDir = 'models/ws-downloads';
        Storage::disk('local')->makeDirectory($wsDir);

        $storedPath = $wsDir . '/' . $downloadFile;
        $fullStoredPath = storage_path('app/' . $storedPath);

        return response()->stream(function () use ($downloadUrl, $fullStoredPath) {
            $sendEvent = function (string $type, array $data) {
                echo "event: {$type}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
            };

            // Already cached
            if (file_exists($fullStoredPath)) {
                $sendEvent('done', [
                    'already_cached' => true,
                    'size_human' => $this->humanSize(filesize($fullStoredPath)),
                ]);
                return;
            }

            try {
                // HEAD request to get file size
                $ch = curl_init($downloadUrl);
                curl_setopt_array($ch, [
                    CURLOPT_NOBODY => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 10,
                ]);
                curl_exec($ch);
                $totalSize = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 400) {
                    $sendEvent('error', ['error' => "HTTP {$httpCode}"]);
                    return;
                }

                $sendEvent('start', [
                    'total_size' => $totalSize,
                    'total_human' => $totalSize > 0 ? $this->humanSize($totalSize) : 'unknown',
                ]);

                // Stream download with progress
                $fp = fopen($fullStoredPath, 'wb');
                $downloaded = 0;
                $lastReport = 0;

                $ch = curl_init($downloadUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 300,
                    CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($fp, &$downloaded, &$lastReport, $totalSize, $sendEvent) {
                        $bytes = strlen($data);
                        fwrite($fp, $data);
                        $downloaded += $bytes;

                        // Report progress every 100KB or 5%
                        $threshold = max(102400, $totalSize > 0 ? $totalSize * 0.05 : 102400);
                        if ($downloaded - $lastReport >= $threshold) {
                            $lastReport = $downloaded;
                            $sendEvent('progress', [
                                'downloaded' => $downloaded,
                                'downloaded_human' => $this->humanSize($downloaded),
                                'total' => $totalSize,
                                'percent' => $totalSize > 0 ? round($downloaded / $totalSize * 100) : null,
                            ]);
                        }
                        return $bytes;
                    },
                ]);

                curl_exec($ch);
                $curlError = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fp);

                if ($curlError) {
                    @unlink($fullStoredPath);
                    $sendEvent('error', ['error' => "Download failed: {$curlError}"]);
                    return;
                }

                if ($httpCode >= 400) {
                    @unlink($fullStoredPath);
                    $sendEvent('error', ['error' => "HTTP {$httpCode}"]);
                    return;
                }

                $sendEvent('done', [
                    'already_cached' => false,
                    'size_human' => $this->humanSize(filesize($fullStoredPath)),
                ]);
            } catch (\Exception $e) {
                @unlink($fullStoredPath);
                $sendEvent('error', ['error' => "Download failed: {$e->getMessage()}"]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function compare(Request $request)
    {
        $request->validate([
            'download_file' => 'required|string',
            'local_path' => 'nullable|string',
            'detail_url' => 'nullable|string',
        ]);

        $downloadFile = $request->input('download_file');
        $localPath = $request->input('local_path');
        $detailUrl = $request->input('detail_url');

        $this->currentDownloadFile = $downloadFile;

        $wsDir = 'models/ws-downloads';
        $storedPath = $wsDir . '/' . $downloadFile;
        $fullStoredPath = storage_path('app/' . $storedPath);

        if (!file_exists($fullStoredPath)) {
            return response()->json(['error' => 'File not downloaded yet'], 422);
        }

        // Inspect the downloaded file
        $wsContents = $this->inspectArchive($fullStoredPath);
        $wsMd5 = md5_file($fullStoredPath);
        $wsSize = filesize($fullStoredPath);

        // If the file itself is a PK3, run model detection on it directly
        $ext = strtolower(pathinfo($fullStoredPath, PATHINFO_EXTENSION));
        if ($ext === 'pk3') {
            $pk3Analysis = $this->detectModelsInPk3($fullStoredPath, $downloadFile);
            // Wrap as a single PK3 entry with models_found so checkAllContentsIdentical works
            $wsContents = [[
                'name' => basename($fullStoredPath),
                'size' => $wsSize,
                'size_human' => $this->humanSize($wsSize),
                'md5' => $wsMd5,
                'type' => 'archive',
                'models_found' => $pk3Analysis['models'] ?? $pk3Analysis,
                'pk3_files' => $pk3Analysis['pk3_files'] ?? [],
                'pk3_summary' => $pk3Analysis['pk3_summary'] ?? [],
                'pk3_total_files' => $pk3Analysis['total_files'] ?? 0,
                'contents' => $wsContents,
                // Preserve text files from inspectArchive
                'pk3_text_files' => collect($wsContents)->filter(fn($e) => !empty($e['text_content']))->map(fn($e) => [
                    'name' => $e['name'],
                    'text_content' => $e['text_content'],
                ])->values()->all(),
            ]];
        }

        $result = [
            'ws_file' => [
                'path' => $storedPath,
                'full_path' => $fullStoredPath,
                'md5' => $wsMd5,
                'size' => $wsSize,
                'size_human' => $this->humanSize($wsSize),
                'contents' => $wsContents,
            ],
            'local_file' => null,
            'differences' => [],
        ];

        // Compare with local if available
        if ($localPath) {
            $localFullPath = storage_path('app/' . $localPath);

            if (file_exists($localFullPath)) {
                $localContents = $this->inspectArchive($localFullPath);
                $localMd5 = md5_file($localFullPath);
                $localSize = filesize($localFullPath);

                $result['local_file'] = [
                    'path' => $localPath,
                    'full_path' => $localFullPath,
                    'md5' => $localMd5,
                    'size' => $localSize,
                    'size_human' => $this->humanSize($localSize),
                    'contents' => $localContents,
                ];

                // For direct PK3 files, wsContents is wrapped — use inner contents for diff
                $wsForDiff = ($ext === 'pk3' && !empty($wsContents[0]['contents']))
                    ? $wsContents[0]['contents']
                    : $wsContents;
                $result['differences'] = $this->computeDifferences(
                    $this->flattenContents($wsForDiff),
                    $this->flattenContents($localContents)
                );
            } else {
                $result['local_file'] = ['error' => "File not found: {$localFullPath}"];
            }
        }

        // Auto-link bundle_uuid for all DB models found in this ZIP
        $numericIds = $this->collectDbIdNumbers($wsContents);
        if (count($numericIds) >= 2) {
            $existingUuid = PlayerModel::whereIn('id', $numericIds)
                ->whereNotNull('bundle_uuid')
                ->value('bundle_uuid');
            $bundleUuid = $existingUuid ?: \Str::uuid()->toString();
            PlayerModel::whereIn('id', $numericIds)->update(['bundle_uuid' => $bundleUuid]);
        }

        // Scrape metadata from ws.q3df.org and update DB models missing author
        if ($detailUrl) {
            $allDbIds = $this->collectDbIdNumbers($wsContents);
            if (!empty($allDbIds)) {
                $needsUpdate = PlayerModel::whereIn('id', $allDbIds)
                    ->where(fn ($q) => $q->where('author', 'Unknown')->orWhereNull('author'))
                    ->exists();

                if ($needsUpdate) {
                    $wsMeta = $this->scrapeWsModelMetadata($detailUrl);
                    if (!empty($wsMeta)) {
                        $updates = [];
                        if (!empty($wsMeta['author'])) $updates['author'] = $wsMeta['author'];

                        if (!empty($updates)) {
                            PlayerModel::whereIn('id', $allDbIds)
                                ->where(fn ($q) => $q->where('author', 'Unknown')->orWhereNull('author'))
                                ->update($updates);
                            $result['ws_metadata_applied'] = $wsMeta;
                        }
                    }
                }
            }
        }

        // Check if all PK3s have identical content to DB (frontend will ask for confirmation)
        $allIdentical = $this->checkAllContentsIdentical($wsContents);
        if ($allIdentical) {
            $dbIds = $this->collectDbIds($wsContents);
            $result['all_identical'] = true;
            $result['identical_db_ids'] = $dbIds;
        }

        return response()->json($result);
    }

    /**
     * Recursively check if all PK3s in contents have all_identical models.
     */
    private function checkAllContentsIdentical(array $contents): bool
    {
        $foundPk3 = false;
        foreach ($contents as $entry) {
            // Skip entries marked as extras (e.g. bot PK3s without models)
            if (!empty($entry['is_extra'])) continue;

            if (!empty($entry['models_found'])) {
                $foundPk3 = true;
                foreach ($entry['models_found'] as $mf) {
                    if (!empty($mf['error']) || !empty($mf['info'])) return false;
                    if (empty($mf['all_identical'])) return false;
                }
            }
            if (!empty($entry['contents'])) {
                if ($this->hasAnyPk3($entry['contents']) && !$this->checkAllContentsIdentical($entry['contents'])) return false;
            }
        }
        return $foundPk3;
    }

    private function hasAnyPk3(array $contents): bool
    {
        foreach ($contents as $entry) {
            if (!empty($entry['models_found'])) return true;
            if (!empty($entry['contents']) && $this->hasAnyPk3($entry['contents'])) return true;
        }
        return false;
    }

    private function hasAnyModelsRecursive(array $contents): bool
    {
        foreach ($contents as $entry) {
            if (!empty($entry['models_found'])) {
                $hasReal = collect($entry['models_found'])->contains(fn ($mf) => !empty($mf['model_name']));
                if ($hasReal) return true;
            }
            if (!empty($entry['contents']) && $this->hasAnyModelsRecursive($entry['contents'])) return true;
        }
        return false;
    }

    private function collectDbIds(array $contents): string
    {
        $ids = [];
        foreach ($contents as $entry) {
            if (!empty($entry['models_found'])) {
                foreach ($entry['models_found'] as $mf) {
                    foreach ($mf['skins'] ?? [] as $sk) {
                        if (!empty($sk['db_id'])) $ids[] = '#' . $sk['db_id'];
                    }
                }
            }
            if (!empty($entry['contents'])) {
                $nested = $this->collectDbIds($entry['contents']);
                if ($nested) $ids[] = $nested;
            }
        }
        return implode(', ', array_unique($ids));
    }

    private function collectDbIdNumbers(array $contents): array
    {
        $ids = [];
        foreach ($contents as $entry) {
            if (!empty($entry['models_found'])) {
                foreach ($entry['models_found'] as $mf) {
                    foreach ($mf['skins'] ?? [] as $sk) {
                        if (!empty($sk['db_id'])) $ids[] = $sk['db_id'];
                    }
                }
            }
            if (!empty($entry['contents'])) {
                $ids = array_merge($ids, $this->collectDbIdNumbers($entry['contents']));
            }
        }
        return array_unique($ids);
    }

    /**
     * Save text content to model description(s).
     */
    public function saveDescription(Request $request)
    {
        $request->validate([
            'model_ids' => 'required|array',
            'model_ids.*' => 'integer',
            'text' => 'required|string|max:65000',
            'append' => 'boolean',
        ]);

        $modelIds = $request->input('model_ids');
        $text = $request->input('text');
        $append = $request->boolean('append', true);

        $updated = [];
        $skipped = [];
        $cleaned = [];
        foreach ($modelIds as $id) {
            $model = PlayerModel::find($id);
            if (!$model) continue;

            // Auto-cleanup: remove duplicate "Author notes" blocks
            if ($model->description && substr_count($model->description, '--- Author notes ---') > 1) {
                $parts = preg_split('/\n*--- Author notes ---\n/', $model->description);
                $base = array_shift($parts);
                // Deduplicate the author notes blocks
                $uniqueParts = array_unique(array_map('trim', $parts));
                $model->description = trim($base) . "\n\n--- Author notes ---\n" . implode("\n\n--- Author notes ---\n", $uniqueParts);
                $model->save();
                $cleaned[] = '#' . $id;
            }

            // Skip if this exact text is already in the description
            if ($model->description && str_contains($model->description, trim($text))) {
                $skipped[] = '#' . $id;
                continue;
            }

            // Build new description
            if ($append && $model->description) {
                $newDescription = $model->description . "\n\n--- Author notes ---\n" . $text;
            } else {
                $newDescription = $text;
            }

            $model->description = $newDescription;
            $model->save();
            $updated[] = '#' . $id;
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped,
            'cleaned' => $cleaned,
        ]);
    }

    /**
     * Build extras ZIP from non-PK3 files in a downloaded ZIP and assign to models.
     */
    public function buildExtrasZip(Request $request)
    {
        $request->validate([
            'download_file' => 'required|string',
            'model_ids' => 'required|array',
            'model_ids.*' => 'integer',
        ]);

        $downloadFile = $request->input('download_file');
        $modelIds = $request->input('model_ids');

        $fullPath = storage_path('app/models/ws-downloads/' . $downloadFile);
        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'ZIP file not found'], 404);
        }

        $zip = new \ZipArchive();
        if ($zip->open($fullPath) !== true) {
            return response()->json(['error' => 'Cannot open ZIP'], 422);
        }

        // Inspect to find which entries are extras (includes bot PK3s without models)
        $inspected = $this->inspectArchive($fullPath);
        $extraNames = collect($inspected)->filter(fn ($e) => !empty($e['is_extra']))->pluck('name')->toArray();

        // Collect extra files from ZIP
        $extraFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '/')) continue; // skip dirs
            if (in_array(basename($name), $extraNames) || in_array($name, $extraNames)) {
                $extraFiles[] = $name;
            }
        }

        if (empty($extraFiles)) {
            $zip->close();
            return response()->json(['error' => 'No extra files found in ZIP'], 422);
        }

        // Extract to temp and build extras ZIP
        $tempDir = sys_get_temp_dir() . '/extras_build_' . md5($fullPath . microtime());
        @mkdir($tempDir, 0777, true);
        $zip->extractTo($tempDir);
        $zip->close();

        $slug = \Str::slug(pathinfo($downloadFile, PATHINFO_FILENAME));
        $extrasDir = storage_path('app/models/extras');
        if (!file_exists($extrasDir)) {
            mkdir($extrasDir, 0755, true);
        }

        $extrasZipPath = $extrasDir . '/' . $slug . '-extras.zip';
        $extrasZip = new \ZipArchive();
        if ($extrasZip->open($extrasZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->removeDir($tempDir);
            return response()->json(['error' => 'Failed to create extras ZIP'], 500);
        }

        foreach ($extraFiles as $name) {
            $filePath = $tempDir . '/' . $name;
            if (file_exists($filePath)) {
                $extrasZip->addFile($filePath, $name);
            }
        }
        $extrasZip->close();
        $this->removeDir($tempDir);

        $storagePath = 'models/extras/' . $slug . '-extras.zip';

        // Assign to models (skip if already has same extras_zip_path)
        $updated = [];
        $skipped = [];
        foreach ($modelIds as $id) {
            $model = PlayerModel::find($id);
            if (!$model) continue;

            if ($model->extras_zip_path === $storagePath) {
                $skipped[] = '#' . $id;
                continue;
            }

            $model->extras_zip_path = $storagePath;
            $model->save();
            $updated[] = '#' . $id;
        }

        return response()->json([
            'success' => true,
            'extras_zip_path' => $storagePath,
            'file_count' => count($extraFiles),
            'files' => $extraFiles,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Mark an audit item as resolved. Updates the JSON report file.
     */
    public function cachedFiles()
    {
        $wsDir = storage_path('app/models/ws-downloads');
        if (!is_dir($wsDir)) {
            return response()->json([]);
        }
        $files = array_values(array_filter(scandir($wsDir), fn($f) => $f !== '.' && $f !== '..'));
        return response()->json($files);
    }

    /**
     * Validate that local PK3 files exist and MD5 matches for resolved OK items.
     */
    public function validateLocalFiles(Request $request)
    {
        $items = $request->input('items', []);
        $results = [];

        foreach ($items as $item) {
            $downloadFile = $item['download_file'] ?? '';
            $localPath = $item['local_path'] ?? null;
            $wsMd5 = $item['ws_md5'] ?? null;

            if (!$localPath) {
                $results[] = ['download_file' => $downloadFile, 'valid' => false, 'reason' => 'No local_path stored'];
                continue;
            }

            $fullPath = storage_path('app/' . $localPath);
            if (!file_exists($fullPath)) {
                $results[] = ['download_file' => $downloadFile, 'valid' => false, 'reason' => "File missing: {$localPath}"];
                continue;
            }

            if ($wsMd5) {
                $localMd5 = md5_file($fullPath);
                if ($localMd5 !== $wsMd5) {
                    $results[] = ['download_file' => $downloadFile, 'valid' => false, 'reason' => "MD5 mismatch: ws={$wsMd5} local={$localMd5}"];
                    continue;
                }
            }

            // Check that at least one DB model references this file
            $dbCount = PlayerModel::where('zip_path', $localPath)->count();
            if ($dbCount === 0) {
                $results[] = ['download_file' => $downloadFile, 'valid' => false, 'reason' => "No DB model references {$localPath}"];
                continue;
            }

            $results[] = ['download_file' => $downloadFile, 'valid' => true];
        }

        return response()->json($results);
    }

    public function markManualReview(Request $request)
    {
        $request->validate(['download_file' => 'required|string']);
        $downloadFile = $request->input('download_file');

        $files = collect(Storage::disk('local')->files('.'))
            ->filter(fn ($f) => str_starts_with(basename($f), 'models-audit-') && str_ends_with($f, '.json'))
            ->sortDesc()
            ->values();

        if ($files->isEmpty()) {
            return response()->json(['error' => 'No audit report found'], 404);
        }

        $reportFile = $files->first();
        $report = json_decode(Storage::disk('local')->get($reportFile), true);

        $found = false;
        foreach ($report['results'] as &$item) {
            if ($item['download_file'] === $downloadFile) {
                $item['manual_review'] = true;
                $found = true;
            }
        }
        unset($item);

        if (!$found) {
            return response()->json(['error' => 'Item not found in report'], 404);
        }

        Storage::disk('local')->put($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json(['success' => true]);
    }

    public function markFailedManual(Request $request)
    {
        $request->validate(['download_file' => 'required|string']);
        $downloadFile = $request->input('download_file');

        $files = collect(Storage::disk('local')->files('.'))
            ->filter(fn ($f) => str_starts_with(basename($f), 'models-audit-') && str_ends_with($f, '.json'))
            ->sortDesc()
            ->values();

        if ($files->isEmpty()) {
            return response()->json(['error' => 'No audit report found'], 404);
        }

        $reportFile = $files->first();
        $report = json_decode(Storage::disk('local')->get($reportFile), true);

        $found = false;
        foreach ($report['results'] as &$item) {
            if ($item['download_file'] === $downloadFile) {
                $item['failed_manual'] = true;
                $found = true;
            }
        }
        unset($item);

        if (!$found) {
            return response()->json(['error' => 'Item not found in report'], 404);
        }

        Storage::disk('local')->put($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json(['success' => true]);
    }

    public function resolve(Request $request)
    {
        $request->validate([
            'download_file' => 'required|string',
            'resolution' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $downloadFile = $request->input('download_file');
        $resolution = $request->input('resolution');
        $note = $request->input('note', '');

        $files = collect(Storage::disk('local')->files('.'))
            ->filter(fn ($f) => str_starts_with(basename($f), 'models-audit-') && str_ends_with($f, '.json'))
            ->sortDesc()
            ->values();

        if ($files->isEmpty()) {
            return response()->json(['error' => 'No audit report found'], 404);
        }

        $reportFile = $files->first();
        $content = Storage::disk('local')->get($reportFile);
        $report = json_decode($content, true);

        // Backup before modifying
        $backupFile = 'audit-backups/' . pathinfo($reportFile, PATHINFO_FILENAME) . '.backup-' . now()->format('His') . '.json';
        Storage::disk('local')->put($backupFile, $content);

        $found = false;
        $isUnresolve = $resolution === 'unresolved';

        foreach ($report['results'] as &$item) {
            if ($item['download_file'] === $downloadFile) {
                if ($isUnresolve) {
                    $item['resolved'] = false;
                    unset($item['resolution'], $item['resolution_note'], $item['resolved_at']);
                    $item['unresolved_at'] = now()->toIso8601String();
                    $item['unresolve_reason'] = $note;
                } else {
                    $item['resolved'] = true;
                    $item['resolution'] = $resolution;
                    $item['resolution_note'] = $note;
                    $item['resolved_at'] = now()->toIso8601String();
                }
                $found = true;
            }
        }
        unset($item);

        if (!$found) {
            return response()->json(['error' => 'Item not found in report'], 404);
        }

        Storage::disk('local')->put($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Verify the write actually persisted
        $verify = json_decode(Storage::disk('local')->get($reportFile), true);
        $verified = false;
        foreach ($verify['results'] ?? [] as $v) {
            if ($v['download_file'] === $downloadFile) {
                if ($isUnresolve) {
                    $verified = empty($v['resolved']);
                } else {
                    $verified = !empty($v['resolved']);
                }
                break;
            }
        }

        if (!$verified) {
            return response()->json(['error' => 'Write verification failed - data did not persist to file'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Import a PK3 from ws-downloads into the database, using the same logic as the scraper.
     */
    public function importModel(Request $request)
    {
        $request->validate([
            'download_file' => 'required|string',
            'name' => 'required|string',
        ]);

        $downloadFile = $request->input('download_file');
        $name = $request->input('name');

        $wsPath = storage_path('app/models/ws-downloads/' . $downloadFile);
        if (!file_exists($wsPath)) {
            return response()->json(['error' => 'File not found in ws-downloads. Use Inspect first.'], 422);
        }

        // Generate slug from filename
        $slug = Str::slug(pathinfo($downloadFile, PATHINFO_FILENAME)) . '-' . time() . '-' . rand(1000, 9999);

        // Copy PK3 to models/pk3s/
        $pk3StoredPath = 'models/pk3s/' . $slug . '.pk3';
        Storage::disk('local')->put($pk3StoredPath, file_get_contents($wsPath));

        // Extract to models/extracted/
        $extractDir = storage_path('app/public/models/extracted/' . $slug);
        if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            $parentDir = dirname($extractDir);
            $parentPerms = substr(sprintf('%o', fileperms($parentDir)), -4);
            $parentOwner = posix_getpwuid(fileowner($parentDir))['name'] ?? fileowner($parentDir);
            $currentUser = posix_getpwuid(posix_geteuid())['name'] ?? posix_geteuid();
            return response()->json([
                'error' => "Failed to create directory. Parent: {$parentDir} (perms: {$parentPerms}, owner: {$parentOwner}), running as: {$currentUser}",
            ], 500);
        }

        $zip = new ZipArchive();
        if ($zip->open(storage_path('app/' . $pk3StoredPath)) !== true) {
            return response()->json(['error' => 'Cannot open PK3 file'], 422);
        }
        $zip->extractTo($extractDir);
        $zip->close();

        // Use the scraper's processing logic (with NullOutput to prevent CLI output errors)
        $scraper = new ScrapeQ3dfModels();

        // Generate manifest.json for case-insensitive file resolution in the 3D viewer
        $scraper->generateFileManifest($extractDir);
        $nullOutput = new NullOutput();
        $outputStyle = new \Illuminate\Console\OutputStyle(new ArrayInput([]), $nullOutput);
        $scraper->setOutput($outputStyle);
        $userId = auth()->id() ?? 1;

        $modelData = [
            'name' => $name,
            'author' => 'Unknown',
        ];

        $createdModels = $scraper->processExtractedPk3(
            $extractDir,
            $slug,
            $pk3StoredPath,
            $modelData,
            $userId
        );

        if (empty($createdModels)) {
            // Cleanup
            Storage::disk('local')->delete($pk3StoredPath);
            $this->removeDir($extractDir);

            return response()->json(['error' => 'No valid model skins found in PK3'], 422);
        }

        // Auto-resolve the audit item
        $this->resolveAuditItem($downloadFile, 'imported', 'Imported as: ' . collect($createdModels)->pluck('name')->join(', '));

        // Handle bundle_uuid if this is a multi-PK3 ZIP
        $bundleUuid = $this->ensureBundleUuid($wsPath, $downloadFile, $createdModels);

        return response()->json([
            'success' => true,
            'models' => $createdModels,
            'bundle_uuid' => $bundleUuid,
        ]);
    }

    /**
     * Import a specific PK3 from an already-downloaded ZIP into the DB.
     * Also creates bundle_uuid links between all models in the same ZIP.
     */
    public function importPk3(Request $request)
    {
        $request->validate([
            'download_file' => 'required|string',  // The ZIP filename in ws-downloads
            'pk3_name' => 'required|string',        // The PK3 filename inside the ZIP
            'detail_url' => 'nullable|string',      // ws.q3df.org detail page URL
        ]);

        $downloadFile = $request->input('download_file');
        $pk3Name = $request->input('pk3_name');
        $detailUrl = $request->input('detail_url');

        $wsPath = storage_path('app/models/ws-downloads/' . $downloadFile);
        if (!file_exists($wsPath)) {
            return response()->json(['error' => 'ZIP not found in ws-downloads. Use Inspect first.'], 422);
        }

        // Extract the specific PK3 from the ZIP to a temp location
        $zip = new ZipArchive();
        if ($zip->open($wsPath) !== true) {
            return response()->json(['error' => 'Cannot open ZIP file'], 422);
        }

        $tempDir = sys_get_temp_dir() . '/audit_import_pk3_' . md5($wsPath . $pk3Name . microtime());
        @mkdir($tempDir, 0777, true);

        // Find and extract the PK3
        $pk3Found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (basename($name) === $pk3Name || $name === $pk3Name) {
                $zip->extractTo($tempDir, $name);
                $pk3Found = true;
                $pk3Name = $name; // Use full path from ZIP
                break;
            }
        }
        $zip->close();

        if (!$pk3Found) {
            $this->removeDir($tempDir);
            return response()->json(['error' => "PK3 '{$pk3Name}' not found in ZIP"], 422);
        }

        $extractedPk3Path = $tempDir . '/' . $pk3Name;
        if (!file_exists($extractedPk3Path)) {
            $this->removeDir($tempDir);
            return response()->json(['error' => 'Failed to extract PK3 from ZIP'], 500);
        }

        // Generate slug and copy PK3 to permanent storage
        $slug = Str::slug(pathinfo($pk3Name, PATHINFO_FILENAME)) . '-' . time() . '-' . rand(1000, 9999);
        $pk3StoredPath = 'models/pk3s/' . $slug . '.pk3';
        Storage::disk('local')->put($pk3StoredPath, file_get_contents($extractedPk3Path));

        // Extract to models/extracted/
        $extractDir = storage_path('app/public/models/extracted/' . $slug);
        if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            $this->removeDir($tempDir);
            return response()->json(['error' => 'Failed to create extraction directory'], 500);
        }

        $pk3Zip = new ZipArchive();
        if ($pk3Zip->open(storage_path('app/' . $pk3StoredPath)) !== true) {
            $this->removeDir($tempDir);
            return response()->json(['error' => 'Cannot open extracted PK3'], 422);
        }
        $pk3Zip->extractTo($extractDir);
        $pk3Zip->close();

        // Merge case-duplicate directories (Linux is case-sensitive, PK3s often mix cases)
        $this->mergeCaseDuplicateDirs($extractDir);

        // Process with scraper logic
        $scraper = new ScrapeQ3dfModels();
        $scraper->generateFileManifest($extractDir);
        $nullOutput = new NullOutput();
        $outputStyle = new \Illuminate\Console\OutputStyle(new ArrayInput([]), $nullOutput);
        $scraper->setOutput($outputStyle);
        $userId = auth()->id() ?? 1;

        // Use the detected model name from the PK3 contents (e.g. "sandy"), not the PK3 filename (e.g. "md3_sandy")
        $detectedNames = $scraper->detectAllModelNames($extractDir);
        $displayName = !empty($detectedNames) ? ucfirst($detectedNames[0]) : ucfirst(pathinfo($pk3Name, PATHINFO_FILENAME));

        // Scrape metadata from ws.q3df.org detail page
        $wsMeta = $detailUrl ? $this->scrapeWsModelMetadata($detailUrl) : [];

        $modelData = [
            'name' => $wsMeta['name'] ?? $displayName,
            'author' => $wsMeta['author'] ?? 'Unknown',
        ];

        $createdModels = $scraper->processExtractedPk3(
            $extractDir, $slug, $pk3StoredPath, $modelData, $userId
        );

        $this->removeDir($tempDir);

        if (empty($createdModels)) {
            Storage::disk('local')->delete($pk3StoredPath);
            $this->removeDir($extractDir);
            return response()->json(['error' => 'No valid model skins found in PK3'], 422);
        }

        // Now handle bundle_uuid: link all models from this ZIP together
        $bundleUuid = $this->ensureBundleUuid($wsPath, $downloadFile, $createdModels);

        // Copy metadata (author, description, extras) from sibling models in same bundle
        $newModelIds = array_column($createdModels, 'id');
        $this->copySiblingMetadata($newModelIds, $bundleUuid);

        return response()->json([
            'success' => true,
            'models' => $createdModels,
            'bundle_uuid' => $bundleUuid,
        ]);
    }

    /**
     * Ensure all models from the same ZIP share a bundle_uuid.
     * Creates one if needed, assigns to new + existing models.
     */
    private function ensureBundleUuid(string $zipPath, string $downloadFile, array $newModels): ?string
    {
        // Find all PK3s in the ZIP and their models in DB
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) return null;

        $pk3Names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pk3') {
                $pk3Names[] = $name;
            }
        }
        $zip->close();

        if (count($pk3Names) < 2) return null; // No bundle needed for single PK3

        // Find all existing DB models that came from PK3s in this same ZIP
        // We detect them by inspecting each PK3 and matching base_model+skin
        $allDbModelIds = [];

        // Add newly created models
        foreach ($newModels as $m) {
            $allDbModelIds[] = $m['id'];
        }

        // For each PK3 in the ZIP, detect models and find them in DB
        $tempDir = sys_get_temp_dir() . '/audit_bundle_' . md5($zipPath . microtime());
        @mkdir($tempDir, 0777, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            foreach ($pk3Names as $pk3Name) {
                $zip->extractTo($tempDir, $pk3Name);
                $pk3Path = $tempDir . '/' . $pk3Name;
                if (!file_exists($pk3Path)) continue;

                // Extract PK3 to detect models
                $pk3TempDir = $tempDir . '/pk3_' . md5($pk3Name);
                @mkdir($pk3TempDir, 0777, true);
                $pk3Zip = new ZipArchive();
                if ($pk3Zip->open($pk3Path) === true) {
                    $pk3Zip->extractTo($pk3TempDir);
                    $pk3Zip->close();

                    $scraper = new ScrapeQ3dfModels();
                    $modelNames = $scraper->detectAllModelNames($pk3TempDir);

                    foreach ($modelNames as $modelName) {
                        $metadata = $scraper->parseModelMetadata($pk3TempDir, $modelName);
                        $skins = $metadata['available_skins'] ?? ['default'];

                        foreach ($skins as $skinName) {
                            $dbModel = PlayerModel::where('base_model', strtolower($modelName))
                                ->where('available_skins', 'like', '%' . $skinName . '%')
                                ->get(['id', 'available_skins'])
                                ->first(function ($m) use ($skinName) {
                                    $skins = $m->available_skins;
                                    if (is_string($skins)) $skins = json_decode($skins, true) ?? [];
                                    return in_array($skinName, $skins);
                                });
                            if ($dbModel) {
                                $allDbModelIds[] = $dbModel->id;
                            }
                        }
                    }
                }
            }
            $zip->close();
        }

        $this->removeDir($tempDir);

        $allDbModelIds = array_unique($allDbModelIds);
        if (count($allDbModelIds) < 2) return null;

        // Check if any existing model already has a bundle_uuid
        $existingUuid = PlayerModel::whereIn('id', $allDbModelIds)
            ->whereNotNull('bundle_uuid')
            ->value('bundle_uuid');

        $bundleUuid = $existingUuid ?: Str::uuid()->toString();

        // Assign to all models
        PlayerModel::whereIn('id', $allDbModelIds)->update(['bundle_uuid' => $bundleUuid]);

        return $bundleUuid;
    }

    /**
     * Scrape model metadata (author, name, release date) from ws.q3df.org detail page.
     */
    private function scrapeWsModelMetadata(string $detailUrl): array
    {
        try {
            $response = Http::withoutVerifying()->timeout(10)->get($detailUrl);
            if (!$response->ok()) return [];

            $html = $response->body();
            $meta = [];

            // Author: <td>Author</td><td><a ...>Author Name</a></td>
            if (preg_match('/<td>Author<\/td>\s*<td>(?:<a[^>]*>)?([^<]+)(?:<\/a>)?<\/td>/i', $html, $m)) {
                $author = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                if ($author) $meta['author'] = $author;
            }

            // Model name: <td>Skin</td><td>Name</td> (first table row is the skin/display name)
            if (preg_match('/<td>Skin<\/td>\s*<td>([^<]+)<\/td>/i', $html, $m)) {
                $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                if ($name) $meta['name'] = $name;
            }

            // Release date
            if (preg_match('/<time datetime="(\d{4}-\d{2}-\d{2})"/', $html, $m)) {
                $meta['release_date'] = $m[1];
            }

            return $meta;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Copy metadata from sibling models (same bundle) to newly imported models.
     */
    private function copySiblingMetadata(array $newModelIds, ?string $bundleUuid): void
    {
        if (!$bundleUuid || empty($newModelIds)) return;

        // Find a sibling with metadata
        $sibling = PlayerModel::where('bundle_uuid', $bundleUuid)
            ->whereNotIn('id', $newModelIds)
            ->where(function ($q) {
                $q->where('author', '!=', 'Unknown')
                    ->orWhereNotNull('description');
            })
            ->first();

        if (!$sibling) return;

        $updates = [];
        if ($sibling->author && $sibling->author !== 'Unknown') {
            $updates['author'] = $sibling->author;
        }
        if ($sibling->description) {
            $updates['description'] = $sibling->description;
        }

        if (!empty($updates)) {
            PlayerModel::whereIn('id', $newModelIds)->update($updates);
        }
    }

    /**
     * Helper to mark an audit item as resolved in the JSON report.
     */
    private function resolveAuditItem(string $downloadFile, string $resolution, string $note): void
    {
        $files = collect(Storage::disk('local')->files('.'))
            ->filter(fn ($f) => str_starts_with(basename($f), 'models-audit-') && str_ends_with($f, '.json'))
            ->sortDesc()
            ->values();

        if ($files->isEmpty()) return;

        $reportFile = $files->first();
        $content = Storage::disk('local')->get($reportFile);
        $report = json_decode($content, true);

        // Backup before modifying
        $backupFile = 'audit-backups/' . pathinfo($reportFile, PATHINFO_FILENAME) . '.backup-' . now()->format('His') . '.json';
        Storage::disk('local')->put($backupFile, $content);

        foreach ($report['results'] as &$item) {
            if ($item['download_file'] === $downloadFile) {
                $item['resolved'] = true;
                $item['resolution'] = $resolution;
                $item['resolution_note'] = $note;
                $item['resolved_at'] = now()->toIso8601String();
            }
        }
        unset($item);

        Storage::disk('local')->put($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Verify write
        $verify = json_decode(Storage::disk('local')->get($reportFile), true);
        foreach ($verify['results'] ?? [] as $v) {
            if ($v['download_file'] === $downloadFile && !empty($v['resolved'])) {
                return; // OK
            }
        }
        \Log::error("resolveAuditItem: Write verification failed for {$downloadFile}");
    }

    /**
     * Inspect a ZIP/PK3 archive recursively.
     * Returns array of file entries with name, size, md5.
     * If a file inside is itself a ZIP/PK3, its contents are nested.
     */
    private function inspectArchive(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($ext, ['zip', 'pk3'])) {
            return [[
                'name' => basename($path),
                'size' => filesize($path),
                'size_human' => $this->humanSize(filesize($path)),
                'md5' => md5_file($path),
                'type' => 'file',
            ]];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [['error' => 'Cannot open archive: ' . basename($path)]];
        }

        $entries = [];
        $tempDir = sys_get_temp_dir() . '/audit_inspect_' . md5($path . microtime());
        @mkdir($tempDir, 0777, true);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            // Skip directories
            if (str_ends_with($name, '/')) continue;

            $entry = [
                'name' => $name,
                'size' => $stat['size'],
                'size_human' => $this->humanSize($stat['size']),
                'type' => 'file',
            ];

            // Check if this is a nested archive
            $innerExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($innerExt, ['zip', 'pk3'])) {
                // Extract to temp and inspect recursively
                $zip->extractTo($tempDir, $name);
                $extractedPath = $tempDir . '/' . $name;

                if (file_exists($extractedPath)) {
                    $innerMd5 = md5_file($extractedPath);
                    $parentBasename = strtolower(pathinfo($path, PATHINFO_FILENAME));
                    $innerBasename = strtolower(pathinfo($name, PATHINFO_FILENAME));

                    // If nested archive has same name as parent, it's redundant packaging — mark as extra
                    if ($innerBasename === $parentBasename) {
                        $entry['md5'] = $innerMd5;
                        $entry['type'] = 'self-reference';
                        $entry['is_extra'] = true;
                        $entry['note'] = 'Duplicate packaging (same name as parent archive)';
                        $entries[] = $entry;
                        continue;
                    }

                    $entry['md5'] = $innerMd5;
                    $entry['type'] = 'archive';
                    $entry['contents'] = $this->inspectArchive($extractedPath);

                    // For PK3 files, detect models and check DB
                    if ($innerExt === 'pk3') {
                        $pk3Analysis = $this->detectModelsInPk3($extractedPath, $entry['name'] ?? null);
                        $entry['models_found'] = $pk3Analysis['models'] ?? $pk3Analysis;
                        $entry['pk3_files'] = $pk3Analysis['pk3_files'] ?? [];
                        $entry['pk3_summary'] = $pk3Analysis['pk3_summary'] ?? [];
                        $entry['pk3_total_files'] = $pk3Analysis['total_files'] ?? 0;
                    }
                }
            } else {
                // Compute MD5 by extracting to temp
                $zip->extractTo($tempDir, $name);
                $extractedPath = $tempDir . '/' . $name;
                if (file_exists($extractedPath)) {
                    $entry['md5'] = md5_file($extractedPath);

                    // Read text file contents (txt, nfo, readme, etc.)
                    $textExts = ['txt', 'nfo', 'readme', 'doc', 'text', 'me'];
                    if (in_array($innerExt, $textExts) || strtolower(basename($name)) === 'readme') {
                        $content = file_get_contents($extractedPath);
                        // Convert to UTF-8 if needed, skip binary files
                        if (!preg_match('/[\x00-\x08\x0E-\x1F]/', $content)) {
                            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,Windows-1252,ISO-8859-1');
                            $entry['text_content'] = mb_substr($content, 0, 10240);
                        }
                    }
                }
            }

            $entries[] = $entry;
        }

        $zip->close();

        // For ZIPs containing PK3s: flag non-PK3 entries and PK3s without models as extra_files
        $hasPk3 = collect($entries)->contains(fn ($e) => ($e['type'] ?? '') === 'archive' && str_ends_with(strtolower($e['name']), '.pk3'));
        if ($hasPk3) {
            foreach ($entries as &$entry) {
                if (($entry['type'] ?? '') !== 'archive') {
                    $entry['is_extra'] = true;
                } elseif (str_ends_with(strtolower($entry['name']), '.pk3')) {
                    // PK3 without real models (e.g. bot files only) → extra
                    $hasRealModels = !empty($entry['models_found']) && collect($entry['models_found'])->contains(fn ($mf) => !empty($mf['model_name']));
                    if (!$hasRealModels) {
                        $entry['is_extra'] = true;
                    }
                } elseif (str_ends_with(strtolower($entry['name']), '.zip')) {
                    // Nested ZIP without any models (e.g. addon/sound ZIPs) → extra
                    $hasModelsInside = $this->hasAnyModelsRecursive($entry['contents'] ?? []);
                    if (!$hasModelsInside) {
                        $entry['is_extra'] = true;
                    }
                }
            }
            unset($entry);

            // Flag lowres PK3s as extra if a full-res version exists
            $pk3Names = collect($entries)
                ->filter(fn ($e) => ($e['type'] ?? '') === 'archive' && str_ends_with(strtolower($e['name']), '.pk3'))
                ->pluck('name')
                ->map(fn ($n) => strtolower(basename($n)))
                ->all();

            foreach ($entries as &$entry) {
                if (!empty($entry['is_extra'])) continue;
                $name = strtolower(basename($entry['name'] ?? ''));
                if (preg_match('/[-_]lowres\.pk3$/i', $name)) {
                    $fullResName = preg_replace('/[-_]lowres\.pk3$/i', '.pk3', $name);
                    if (in_array($fullResName, $pk3Names)) {
                        $entry['is_extra'] = true;
                        $entry['note'] = 'Lowres variant (full-res version exists)';
                    }
                }
            }
            unset($entry);
        }

        // For PK3 entries: detect readme/txt files inside PK3 contents
        foreach ($entries as &$entry) {
            if (($entry['type'] ?? '') === 'archive' && str_ends_with(strtolower($entry['name']), '.pk3') && !empty($entry['contents'])) {
                $pk3Texts = [];
                foreach ($entry['contents'] as $inner) {
                    if (!empty($inner['text_content'])) {
                        $pk3Texts[] = $inner;
                    }
                }
                if (!empty($pk3Texts)) {
                    $entry['pk3_text_files'] = $pk3Texts;
                }
            }
        }
        unset($entry);

        // Cleanup temp
        $this->removeDir($tempDir);

        return $entries;
    }

    /**
     * Extract a PK3 to temp, detect model folders/skins, check if they exist in DB.
     */
    private function detectModelsInPk3(string $pk3Path, ?string $sourceFilename = null): array
    {
        $tempExtract = sys_get_temp_dir() . '/audit_pk3_models_' . md5($pk3Path . microtime());
        @mkdir($tempExtract, 0777, true);

        $zip = new ZipArchive();
        if ($zip->open($pk3Path) !== true) {
            return [['error' => 'Cannot open PK3']];
        }
        $zip->extractTo($tempExtract);
        $zip->close();

        // Merge case-duplicate directories (Linux is case-sensitive, PK3s often mix cases)
        $this->mergeCaseDuplicateDirs($tempExtract);

        $scraper = new ScrapeQ3dfModels();
        $modelNames = $scraper->detectAllModelNames($tempExtract);

        if (empty($modelNames)) {
            $this->removeDir($tempExtract);
            return [['info' => 'No model folders found (no models/players/*/)', 'in_db' => false]];
        }

        // Build map of all PK3 files with MD5s (relative path → md5)
        $pk3Files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempExtract, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($tempExtract . '/', '', $file->getPathname());
                $pk3Files[strtolower($relativePath)] = [
                    'path' => $relativePath,
                    'md5' => md5_file($file->getPathname()),
                    'size' => $file->getSize(),
                ];
            }
        }

        $results = [];
        foreach ($modelNames as $modelName) {
            $metadata = $scraper->parseModelMetadata($tempExtract, $modelName);
            $skins = $metadata['available_skins'] ?? ['default'];

            // Build slugs from source filenames for disambiguation
            $sourceSlugs = [];
            if ($sourceFilename) {
                $sourceSlugs[] = Str::slug(pathinfo(basename($sourceFilename), PATHINFO_FILENAME));
            }
            if ($this->currentDownloadFile) {
                $dlSlug = Str::slug(pathinfo(basename($this->currentDownloadFile), PATHINFO_FILENAME));
                if (!in_array($dlSlug, $sourceSlugs)) {
                    $sourceSlugs[] = $dlSlug;
                }
            }

            // Check DB for each skin
            $skinResults = [];
            foreach ($skins as $skinName) {
                $candidates = PlayerModel::where('base_model', strtolower($modelName))
                    ->where('available_skins', 'like', '%' . $skinName . '%')
                    ->get()
                    ->filter(function ($m) use ($skinName) {
                        $skins = $m->available_skins;
                        if (is_string($skins)) $skins = json_decode($skins, true) ?? [];
                        return in_array($skinName, $skins);
                    });

                // If multiple candidates and we have source slugs, prefer the one whose zip_path matches
                $dbModel = null;
                if ($candidates->count() > 1 && !empty($sourceSlugs)) {
                    foreach ($sourceSlugs as $slug) {
                        // Try exact slug match
                        $dbModel = $candidates->first(function ($m) use ($slug) {
                            if (preg_match('/([^\/]+)-\d{10}-\d+\.pk3$/', $m->zip_path, $match)) {
                                return $match[1] === $slug;
                            }
                            return false;
                        });
                        if ($dbModel) break;
                    }

                    // If no exact match, try contains
                    if (!$dbModel) {
                        foreach ($sourceSlugs as $slug) {
                            $dbModel = $candidates->first(function ($m) use ($slug) {
                                if (preg_match('/([^\/]+)-\d{10}-\d+\.pk3$/', $m->zip_path, $match)) {
                                    $dbSlug = $match[1];
                                    return str_contains($dbSlug, $slug) || str_contains($slug, $dbSlug);
                                }
                                return false;
                            });
                            if ($dbModel) break;
                        }
                    }
                }
                // Fallback to first candidate
                $dbModel = $dbModel ?? $candidates->first();

                $skinResult = [
                    'skin' => $skinName,
                    'in_db' => $dbModel !== null,
                    'db_id' => $dbModel?->id,
                    'db_name' => $dbModel?->name,
                ];

                // If found in DB, compare file MD5s
                if ($dbModel) {
                    $skinResult['file_comparison'] = $this->compareModelFiles(
                        $pk3Files, $tempExtract, $dbModel
                    );
                }

                $skinResults[] = $skinResult;
            }

            $allInDb = collect($skinResults)->every(fn ($s) => $s['in_db']);
            $allIdentical = collect($skinResults)->every(fn ($s) =>
                $s['in_db'] && isset($s['file_comparison']) && $s['file_comparison']['status'] === 'identical'
            );

            $results[] = [
                'model_name' => $modelName,
                'skins' => $skinResults,
                'all_in_db' => $allInDb,
                'all_identical' => $allIdentical,
            ];
        }

        // Build a categorized file listing of everything in the PK3
        $pk3FileList = [];
        foreach ($pk3Files as $lowerPath => $info) {
            $path = $info['path'];
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            // Categorize
            if (str_starts_with($lowerPath, 'models/players/')) {
                $category = 'model';
            } elseif (str_starts_with($lowerPath, 'scripts/')) {
                $category = 'script';
            } elseif (str_starts_with($lowerPath, 'sound/') || str_starts_with($lowerPath, 'sounds/')) {
                $category = 'sound';
            } elseif (str_starts_with($lowerPath, 'botfiles/')) {
                $category = 'botfile';
            } elseif (in_array($ext, ['txt', 'doc', 'nfo', 'readme'])) {
                $category = 'docs';
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'gif']) && !str_starts_with($lowerPath, 'models/')) {
                $category = 'image';
            } else {
                $category = 'other';
            }

            $pk3FileList[] = [
                'path' => $path,
                'size' => $info['size'],
                'category' => $category,
            ];
        }

        // Group by category for summary
        $categorySummary = [];
        foreach ($pk3FileList as $f) {
            $cat = $f['category'];
            if (!isset($categorySummary[$cat])) {
                $categorySummary[$cat] = ['count' => 0, 'size' => 0];
            }
            $categorySummary[$cat]['count']++;
            $categorySummary[$cat]['size'] += $f['size'];
        }
        foreach ($categorySummary as $cat => &$s) {
            $s['size_human'] = $this->humanSize($s['size']);
        }

        $this->removeDir($tempExtract);

        return [
            'models' => $results,
            'pk3_files' => $pk3FileList,
            'pk3_summary' => $categorySummary,
            'total_files' => count($pk3FileList),
        ];
    }

    /**
     * Compare files from a PK3 extraction against our stored model files.
     */
    private function compareModelFiles(array $pk3Files, string $tempExtract, PlayerModel $dbModel): array
    {
        // file_path is like "models/extracted/{slug}/models/players/niria"
        // Extract root is "models/extracted/{slug}/" — contains ALL extracted PK3 files
        $parts = explode('/', $dbModel->file_path);
        $slug = $parts[2] ?? null;
        if (!$slug) {
            return ['status' => 'error', 'message' => 'Cannot determine slug from: ' . $dbModel->file_path];
        }

        $localRoot = storage_path('app/public/models/extracted/' . $slug);
        if (!is_dir($localRoot)) {
            return ['status' => 'error', 'message' => 'Local directory not found: models/extracted/' . $slug];
        }

        // Build map of our local files (relative path → md5)
        $localFiles = [];
        $localIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localRoot, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($localIterator as $file) {
            if ($file->isFile()) {
                $relativePath = strtolower(str_replace($localRoot . '/', '', $file->getPathname()));
                // Skip manifest.json - generated locally, not in PK3
                if ($relativePath === 'manifest.json') continue;
                $localFiles[$relativePath] = [
                    'md5' => md5_file($file->getPathname()),
                    'size' => $file->getSize(),
                ];
            }
        }

        // Compare PK3 files against local files by relative path
        $fileResults = [];
        $allMatch = true;

        foreach ($pk3Files as $lowerPath => $pk3Info) {
            if (isset($localFiles[$lowerPath])) {
                $match = $pk3Info['md5'] === $localFiles[$lowerPath]['md5'];
                if (!$match) $allMatch = false;
                $fileResults[] = [
                    'file' => $pk3Info['path'],
                    'status' => $match ? 'identical' : 'differs',
                    'pk3_md5' => substr($pk3Info['md5'], 0, 8),
                    'local_md5' => substr($localFiles[$lowerPath]['md5'], 0, 8),
                ];
            } else {
                $allMatch = false;
                $fileResults[] = [
                    'file' => $pk3Info['path'],
                    'status' => 'only_in_pk3',
                    'pk3_md5' => substr($pk3Info['md5'], 0, 8),
                ];
            }
        }

        // Check for files only in local
        foreach ($localFiles as $relativePath => $localInfo) {
            if (!isset($pk3Files[$relativePath])) {
                $allMatch = false;
                $fileResults[] = [
                    'file' => $relativePath,
                    'status' => 'only_local',
                    'local_md5' => substr($localInfo['md5'], 0, 8),
                ];
            }
        }

        return [
            'status' => $allMatch ? 'identical' : 'differs',
            'files' => $fileResults,
        ];
    }

    /**
     * Flatten nested contents into a flat list with paths for comparison.
     */
    private function flattenContents(array $contents, string $prefix = ''): array
    {
        $flat = [];
        foreach ($contents as $entry) {
            if (isset($entry['error'])) continue;

            $path = $prefix ? $prefix . '/' . $entry['name'] : $entry['name'];

            $flat[$path] = [
                'size' => $entry['size'] ?? 0,
                'md5' => $entry['md5'] ?? null,
            ];

            if (!empty($entry['contents'])) {
                $nested = $this->flattenContents($entry['contents'], $path);
                $flat = array_merge($flat, $nested);
            }
        }
        return $flat;
    }

    /**
     * Compare two flat file lists and find differences.
     */
    private function computeDifferences(array $wsFlat, array $localFlat): array
    {
        $diffs = [];

        // Files only in ws
        foreach ($wsFlat as $path => $info) {
            if (!isset($localFlat[$path])) {
                $diffs[] = [
                    'file' => $path,
                    'type' => 'only_in_ws',
                    'ws_size' => $info['size'],
                    'ws_md5' => $info['md5'],
                ];
            }
        }

        // Files only in local
        foreach ($localFlat as $path => $info) {
            if (!isset($wsFlat[$path])) {
                $diffs[] = [
                    'file' => $path,
                    'type' => 'only_in_local',
                    'local_size' => $info['size'],
                    'local_md5' => $info['md5'],
                ];
            }
        }

        // Files in both but different
        foreach ($wsFlat as $path => $info) {
            if (isset($localFlat[$path])) {
                $localInfo = $localFlat[$path];
                if ($info['md5'] !== $localInfo['md5'] || $info['size'] !== $localInfo['size']) {
                    $diffs[] = [
                        'file' => $path,
                        'type' => 'different',
                        'ws_size' => $info['size'],
                        'ws_md5' => $info['md5'],
                        'local_size' => $localInfo['size'],
                        'local_md5' => $localInfo['md5'],
                    ];
                }
            }
        }

        return $diffs;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Recursively merge directories that differ only in case.
     * E.g. MODELS/PLAYERS/Foo + models/players/Foo → merge files into one.
     */
    private function mergeCaseDuplicateDirs(string $dir): void
    {
        if (!is_dir($dir)) return;

        $entries = scandir($dir);
        $dirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir($dir . '/' . $entry)) {
                $lower = strtolower($entry);
                $dirs[$lower][] = $entry;
            }
        }

        foreach ($dirs as $lower => $variants) {
            if (count($variants) <= 1) {
                // No duplicates, recurse into it
                $this->mergeCaseDuplicateDirs($dir . '/' . $variants[0]);
                continue;
            }

            // Multiple dirs with same lowercase name — merge into the first one
            $target = $variants[0];
            for ($i = 1; $i < count($variants); $i++) {
                $source = $dir . '/' . $variants[$i];
                $targetDir = $dir . '/' . $target;

                // Move all files from source into target
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $item) {
                    $relPath = substr($item->getPathname(), strlen($source) + 1);
                    $destPath = $targetDir . '/' . $relPath;
                    if ($item->isDir()) {
                        @mkdir($destPath, 0777, true);
                    } else {
                        if (!file_exists($destPath)) {
                            @mkdir(dirname($destPath), 0777, true);
                            rename($item->getPathname(), $destPath);
                        }
                    }
                }
                // Remove empty source dir
                $this->removeDir($source);
            }

            // Recurse into merged dir
            $this->mergeCaseDuplicateDirs($dir . '/' . $target);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Return models missing metadata (author, etc.) matched against WS audit data
     */
    public function wsDetailCheck()
    {
        $report = $this->loadLatestReport();
        $wsResults = $report['results'] ?? [];

        // Index WS results by download_file (lowercase) for matching
        $wsByFile = [];
        foreach ($wsResults as $ws) {
            if (!empty($ws['download_file'])) {
                $wsByFile[strtolower($ws['download_file'])] = $ws;
            }
        }

        // Get all approved models missing author
        $models = PlayerModel::approved()
            ->where(function ($q) {
                $q->whereNull('author')
                  ->orWhere('author', '');
            })
            ->select('id', 'name', 'base_model', 'author', 'available_skins', 'zip_path', 'model_type', 'thumbnail_path')
            ->orderBy('id', 'desc')
            ->get();

        $items = [];
        foreach ($models as $model) {
            $zipBasename = $model->zip_path ? strtolower(basename($model->zip_path)) : null;

            // Try matching by zip_path basename
            $wsMatch = null;
            if ($zipBasename && isset($wsByFile[$zipBasename])) {
                $wsMatch = $wsByFile[$zipBasename];
            }

            // Try matching by pk3 name inside zip (zip might have different name)
            if (!$wsMatch && $zipBasename) {
                $pk3Name = preg_replace('/\.zip$/i', '.pk3', $zipBasename);
                if (isset($wsByFile[$pk3Name])) {
                    $wsMatch = $wsByFile[$pk3Name];
                }
            }

            // Build guessed WS URL from base_model + skin name
            $guessedWsUrl = null;
            if ($model->base_model) {
                $baseModel = strtolower($model->base_model);
                $skins = $model->available_skins;
                if (is_string($skins)) $skins = json_decode($skins, true);
                $skinName = is_array($skins) && !empty($skins) ? strtolower($skins[0]) : null;

                if ($skinName && $skinName !== 'default') {
                    $guessedWsUrl = "https://ws.q3df.org/model/{$baseModel}/skin/{$skinName}/";
                } else {
                    $guessedWsUrl = "https://ws.q3df.org/model/{$baseModel}/";
                }
            }

            $items[] = [
                'id' => $model->id,
                'name' => $model->name,
                'base_model' => $model->base_model,
                'author' => $model->author,
                'model_type' => $model->model_type,
                'thumbnail_path' => $model->thumbnail_path,
                'available_skins' => $model->available_skins,
                'local_url' => "/models/{$model->id}",
                'ws_detail_url' => $wsMatch['detail_url'] ?? null,
                'ws_name' => $wsMatch['name'] ?? null,
                'ws_download_file' => $wsMatch['download_file'] ?? null,
                'guessed_ws_url' => $guessedWsUrl,
            ];
        }

        return response()->json($items);
    }

    /**
     * Return models where name is missing skin in parentheses
     */
    public function missingSkinsInName()
    {
        $models = PlayerModel::approved()
            ->where('hidden', false)
            ->select('id', 'name', 'base_model', 'available_skins', 'model_type', 'author', 'thumbnail_path')
            ->orderBy('id', 'desc')
            ->get();

        $items = [];
        foreach ($models as $model) {
            $skins = $model->available_skins;
            if (is_string($skins)) $skins = json_decode($skins, true);
            if (!is_array($skins) || empty($skins)) continue;

            $skinName = $skins[0];
            if (strtolower($skinName) === 'default') continue;

            // Check if name already contains (skin_name) correctly
            if (stripos($model->name, "({$skinName})") !== false) continue;

            // Build expected name: strip existing parentheses (wrong skin from WS scrape) and add correct one
            $baseName = preg_replace('/\s*\([^)]*\)\s*$/', '', $model->name);
            $expectedName = trim($baseName) . " ({$skinName})";

            $items[] = [
                'id' => $model->id,
                'name' => $model->name,
                'base_model' => $model->base_model,
                'skin_name' => $skinName,
                'expected_name' => $expectedName,
                'model_type' => $model->model_type,
                'author' => $model->author,
                'local_url' => "/models/{$model->id}",
            ];
        }

        return response()->json($items);
    }

    /**
     * Fix model name (add skin in parentheses, etc.)
     */
    public function fixModelName(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'new_name' => 'required|string|max:255',
        ]);

        $model = PlayerModel::findOrFail($request->id);
        $oldName = $model->name;
        $model->name = $request->new_name;
        $model->save();

        return response()->json(['success' => true, 'old_name' => $oldName, 'new_name' => $model->name]);
    }

    private function loadLatestReport(): array
    {
        $files = collect(Storage::disk('local')->files('.'))
            ->filter(fn ($f) => str_starts_with(basename($f), 'models-audit-') && str_ends_with($f, '.json'))
            ->sortDesc()
            ->values();

        if ($files->isEmpty()) {
            return ['stats' => [], 'problems' => [], 'results' => [], 'generated_at' => null];
        }

        $content = Storage::disk('local')->get($files->first());
        return json_decode($content, true) ?? [];
    }
}
