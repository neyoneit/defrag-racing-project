<?php

namespace App\Console\Commands;

use App\Models\PlayerModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AuditModels extends Command
{
    protected $signature = 'models:audit
        {--limit=0 : Limit number of models to check (0 = all)}
        {--start-page=0 : Start from this page index}
        {--sleep=2 : Seconds between requests}';

    protected $description = 'Scrape all ws.q3df.org model pages, extract download URLs and MD5 checksums, compare against our stored PK3 files';

    private string $reportPath;
    private array $stats;
    private array $allResults;
    private array $problems;
    private int $checked;
    private int $skipped;

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $startPage = (int) $this->option('start-page');
        $sleepSeconds = (int) $this->option('sleep');

        $this->reportPath = 'models-audit-' . date('Y-m-d_H-i-s') . '.json';

        // Load all unique stored PK3 paths and precompute their MD5s
        $this->info('Loading local PK3 file MD5 hashes...');
        $localPk3s = $this->loadLocalPk3Md5s();
        $this->info('Loaded ' . count($localPk3s) . ' unique PK3 files');
        $this->newLine();

        $this->stats = [
            'ok' => 0,
            'mismatch' => 0,
            'zip_needs_check' => 0,
            'missing_local' => 0,
            'not_in_db' => 0,
            'scrape_error' => 0,
        ];

        $this->allResults = [];
        $this->problems = [];
        $checkedFiles = []; // Track already-checked download files to skip duplicates
        $this->checked = 0;
        $this->skipped = 0;
        $page = $startPage;
        $allSkippedPages = 0; // Count consecutive pages where all models were already checked

        while (true) {
            if ($limit > 0 && $this->checked >= $limit) break;

            $url = $page === 0
                ? 'https://ws.q3df.org/models/?show=50'
                : "https://ws.q3df.org/models/?model=&page={$page}&show=50";

            $this->info("=== Page {$page} === {$url}");

            try {
                $response = Http::timeout(30)->withOptions(['verify' => false])->get($url);

                if ($response->status() === 429) {
                    $this->warn('Rate limited, waiting 30s...');
                    sleep(30);
                    $response = Http::timeout(30)->withOptions(['verify' => false])->get($url);
                }

                if (!$response->successful()) {
                    $this->error("Failed to fetch page {$page} (HTTP {$response->status()})");
                    break;
                }

                $html = $response->body();
                $models = $this->parseListingPage($html);

                if (empty($models)) {
                    $this->info('No models found on this page, done.');
                    break;
                }

                // Detect last page: if ALL models on page are already checked, count it
                $allAlreadyChecked = true;
                foreach ($models as $m) {
                    if (!isset($checkedFiles[$m['download_file']])) {
                        $allAlreadyChecked = false;
                        break;
                    }
                }

                if ($allAlreadyChecked) {
                    $allSkippedPages++;
                    $this->warn("  All models on page already checked ({$allSkippedPages}/3 consecutive)");
                    if ($allSkippedPages >= 3) {
                        $this->info('3 consecutive fully-skipped pages, last page reached. Done.');
                        $this->info('(To continue from here, use --start-page=' . ($page + 1) . ')');
                        break;
                    }
                    $page++;
                    continue;
                } else {
                    $allSkippedPages = 0;
                }

                $this->info('Found ' . count($models) . ' models on page');

                foreach ($models as $model) {
                    if ($limit > 0 && $this->checked >= $limit) break;

                    // Skip if we already checked this download file
                    if (isset($checkedFiles[$model['download_file']])) {
                        $this->skipped++;
                        $this->line("  SKIP (already checked {$model['download_file']})");
                        continue;
                    }
                    $checkedFiles[$model['download_file']] = true;

                    $this->checked++;

                    $this->line("[{$this->checked}] {$model['name']} -> {$model['download_file']}");

                    // Fetch detail page for MD5 and actual download URL
                    sleep($sleepSeconds);

                    $detail = $this->fetchDetailPage($model['detail_url']);
                    if (!$detail) {
                        $this->stats['scrape_error']++;
                        $this->allResults[] = [
                            'name' => $model['name'],
                            'detail_url' => $model['detail_url'],
                            'download_file' => $model['download_file'],
                            'download_url' => $model['download_url'],
                            'status' => 'SCRAPE_ERROR',
                        ];
                        $this->warn("  Could not fetch detail page");
                        continue;
                    }

                    $remoteMd5 = $detail['md5'];
                    $downloadFile = $detail['download_file'] ?? $model['download_file'];
                    $downloadUrl = $detail['download_url'] ?? $model['download_url'];

                    if (!$remoteMd5) {
                        $this->stats['scrape_error']++;
                        $this->allResults[] = [
                            'name' => $model['name'],
                            'detail_url' => $model['detail_url'],
                            'download_file' => $downloadFile,
                            'download_url' => $downloadUrl,
                            'status' => 'NO_MD5',
                        ];
                        $this->warn("  No MD5 on detail page");
                        continue;
                    }

                    $isZip = str_ends_with(strtolower($downloadFile), '.zip');

                    if ($isZip) {
                        // ZIP files can't be compared by MD5 (ws has ZIP md5, we have extracted PK3)
                        $this->stats['zip_needs_check']++;
                        $this->problems[] = [
                            'type' => 'ZIP_NEEDS_CHECK',
                            'name' => $model['name'],
                            'download_file' => $downloadFile,
                            'download_url' => $downloadUrl,
                            'ws_md5' => $remoteMd5,
                        ];
                        $this->allResults[] = [
                            'name' => $model['name'],
                            'detail_url' => $model['detail_url'],
                            'download_file' => $downloadFile,
                            'download_url' => $downloadUrl,
                            'ws_md5' => $remoteMd5,
                            'status' => 'ZIP_NEEDS_CHECK',
                        ];
                        $this->comment("  ZIP_NEEDS_CHECK - {$downloadFile} (must download & inspect manually)");
                        continue;
                    }

                    // Find matching local PK3 by MD5
                    $localMatch = $localPk3s[$remoteMd5] ?? null;

                    if ($localMatch) {
                        $this->stats['ok']++;
                        $this->allResults[] = [
                            'name' => $model['name'],
                            'detail_url' => $model['detail_url'],
                            'download_file' => $downloadFile,
                            'download_url' => $downloadUrl,
                            'ws_md5' => $remoteMd5,
                            'status' => 'OK',
                            'local_path' => $localMatch,
                        ];
                        $this->info("  OK - MD5 {$remoteMd5} matches {$localMatch}");
                    } else {
                        // No MD5 match - check if we have any models with similar name in DB
                        $slug = \Str::slug(pathinfo($downloadFile, PATHINFO_FILENAME));
                        $dbModel = PlayerModel::where('zip_path', 'LIKE', "%{$slug}%")->first(['id', 'name', 'zip_path']);

                        if ($dbModel) {
                            // We have it in DB but MD5 doesn't match
                            $localPath = $dbModel->zip_path;
                            $localFullPath = storage_path('app/' . $localPath);
                            $localMd5 = file_exists($localFullPath) ? md5_file($localFullPath) : 'FILE_MISSING';

                            $this->stats['mismatch']++;
                            $this->problems[] = [
                                'type' => 'MISMATCH',
                                'name' => $model['name'],
                                'download_file' => $downloadFile,
                                'download_url' => $downloadUrl,
                                'ws_md5' => $remoteMd5,
                                'local_md5' => $localMd5,
                                'local_path' => $localPath,
                                'db_model_id' => $dbModel->id,
                            ];
                            $this->allResults[] = [
                                'name' => $model['name'],
                                'detail_url' => $model['detail_url'],
                                'download_file' => $downloadFile,
                                'download_url' => $downloadUrl,
                                'ws_md5' => $remoteMd5,
                                'local_md5' => $localMd5,
                                'local_path' => $localPath,
                                'status' => 'MISMATCH',
                                'db_model_id' => $dbModel->id,
                            ];
                            $this->warn("  MISMATCH - ws:{$remoteMd5} vs local:{$localMd5} ({$localPath})");
                        } else {
                            // Not in our DB at all
                            $this->stats['not_in_db']++;
                            $this->problems[] = [
                                'type' => 'NOT_IN_DB',
                                'name' => $model['name'],
                                'download_file' => $downloadFile,
                                'download_url' => $downloadUrl,
                                'ws_md5' => $remoteMd5,
                            ];
                            $this->allResults[] = [
                                'name' => $model['name'],
                                'detail_url' => $model['detail_url'],
                                'download_file' => $downloadFile,
                                'download_url' => $downloadUrl,
                                'ws_md5' => $remoteMd5,
                                'status' => 'NOT_IN_DB',
                            ];
                            $this->error("  NOT IN DB - {$downloadFile} (MD5: {$remoteMd5})");
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->error("Error on page {$page}: " . $e->getMessage());
                $this->stats['scrape_error']++;
            }

            // Save report after each page so data is not lost on interrupt
            $this->saveReport();

            $page++;
        }

        // Final save
        $this->saveReport();

        $this->newLine();
        $this->info("Report saved to: storage/app/{$this->reportPath}");
        $this->newLine();
        $this->info("╔══════════════════════════════════════╗");
        $this->info("║          AUDIT SUMMARY               ║");
        $this->info("╠══════════════════════════════════════╣");
        $this->info("║  Models checked:    {$this->checked}");
        $this->info("║  Skipped (dupes):   {$this->skipped}");
        $this->info("║  OK (MD5 match):    {$this->stats['ok']}");
        $this->warn("║  MISMATCH (PK3):    {$this->stats['mismatch']}");
        $this->comment("║  ZIP needs check:   {$this->stats['zip_needs_check']}");
        $this->error("║  NOT IN DB:         {$this->stats['not_in_db']}");
        $this->info("║  Scrape errors:     {$this->stats['scrape_error']}");
        $this->info("╚══════════════════════════════════════╝");

        if (!empty($this->problems)) {
            $this->newLine();
            $this->info("=== PROBLEMS ===");
            foreach ($this->problems as $p) {
                $this->warn("[{$p['type']}] {$p['name']} - {$p['download_file']} ({$p['download_url']})");
            }
        }

        return 0;
    }

    private function saveReport(): void
    {
        $report = [
            'generated_at' => now()->toIso8601String(),
            'stats' => $this->stats,
            'problems' => $this->problems,
            'results' => $this->allResults,
        ];
        Storage::disk('local')->put($this->reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Load all unique PK3 files from disk and compute their MD5 hashes.
     * Returns [md5 => relative_path]
     */
    private function loadLocalPk3Md5s(): array
    {
        $pk3Dir = storage_path('app/models/pk3s');
        if (!is_dir($pk3Dir)) return [];

        $result = [];
        $files = scandir($pk3Dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $fullPath = $pk3Dir . '/' . $file;
            if (!is_file($fullPath)) continue;

            $md5 = md5_file($fullPath);
            $result[$md5] = 'models/pk3s/' . $file;
        }

        return $result;
    }

    /**
     * Parse the models listing page HTML
     */
    private function parseListingPage(string $html): array
    {
        $models = [];

        preg_match_all('/<div class="models_modelitem">.*?<\/div>/is', $html, $containers, PREG_SET_ORDER);

        foreach ($containers as $container) {
            $containerHtml = $container[0];

            // Model detail URL and name
            if (!preg_match('/<a href="(\/model\/[^"]+)">([^<]+)<\/a>/', $containerHtml, $modelMatch)) {
                continue;
            }

            // Download URL and filename
            if (!preg_match('/<a href="(\/models\/downloads\/[^"]+\.(pk3|zip))">([^<]+)<\/a>/', $containerHtml, $downloadMatch)) {
                continue;
            }

            $models[] = [
                'name' => trim($modelMatch[2]),
                'detail_url' => 'https://ws.q3df.org' . $modelMatch[1],
                'download_url' => 'https://ws.q3df.org' . $downloadMatch[1],
                'download_file' => trim($downloadMatch[3]),
            ];
        }

        return $models;
    }

    /**
     * Fetch a model detail page and extract MD5 checksum and download info
     */
    private function fetchDetailPage(string $url): ?array
    {
        try {
            $response = Http::timeout(15)->withOptions(['verify' => false])->get($url);

            if ($response->status() === 429) {
                $this->warn('  Rate limited on detail page, waiting 15s...');
                sleep(15);
                $response = Http::timeout(15)->withOptions(['verify' => false])->get($url);
            }

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Extract MD5: <td>Checksum</td><td>MD5: 69228254190b7f0373346f9d7e010a7b</td>
            $md5 = null;
            if (preg_match('/Checksum<\/td>\s*<td>MD5:\s*([a-f0-9]{32})/i', $html, $md5Match)) {
                $md5 = strtolower(trim($md5Match[1]));
            }

            // Extract download URL from detail page (more reliable than listing)
            $downloadFile = null;
            $downloadUrl = null;
            if (preg_match('/<a href="(\/models\/downloads\/([^"]+))"[^>]*class="modeldetails_bigdownloadlink"/', $html, $dlMatch)) {
                $downloadUrl = 'https://ws.q3df.org' . $dlMatch[1];
                $downloadFile = $dlMatch[2];
            }

            return [
                'md5' => $md5,
                'download_file' => $downloadFile,
                'download_url' => $downloadUrl,
            ];

        } catch (\Exception $e) {
            return null;
        }
    }
}
