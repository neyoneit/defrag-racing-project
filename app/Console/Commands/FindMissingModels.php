<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FindMissingModels extends Command
{
    protected $signature = 'models:find-missing
        {--sleep=1 : Seconds between page requests}
        {--max-pages=300 : Maximum pages to scrape}';

    protected $description = 'Scrape ws.q3df.org model listing pages and find download files we don\'t have in our downloaded history';

    public function handle()
    {
        $sleepSeconds = (int) $this->option('sleep');
        $maxPages = (int) $this->option('max-pages');

        // Load downloaded history
        $historyPath = storage_path('app/bulk-upload/downloaded_history.json');
        $history = [];
        if (file_exists($historyPath)) {
            $history = json_decode(file_get_contents($historyPath), true) ?? [];
        }
        $this->info('Downloaded history entries: ' . count($history));

        // Also load local pk3 files for cross-reference
        $localPk3s = [];
        $pk3Dir = storage_path('app/models/pk3s');
        if (is_dir($pk3Dir)) {
            foreach (scandir($pk3Dir) as $file) {
                if ($file === '.' || $file === '..') continue;
                // Strip the timestamp suffix: "name-1234567890-1234.pk3" -> "name"
                $baseName = preg_replace('/-\d+-\d+\.pk3$/', '', $file);
                $localPk3s[$baseName] = $file;
            }
        }
        $this->info('Local PK3 files: ' . count($localPk3s));

        $allWsModels = [];
        $missing = [];
        $found = 0;
        $page = 0;
        $emptyPages = 0;

        while ($page < $maxPages) {
            $url = $page === 0
                ? 'https://ws.q3df.org/models/?show=50'
                : "https://ws.q3df.org/models/?model=&page={$page}&show=50";

            $this->line("Page {$page}...");

            try {
                $response = Http::timeout(30)->withOptions(['verify' => false])->get($url);

                if ($response->status() === 429) {
                    $this->warn('Rate limited, waiting 30s...');
                    sleep(30);
                    $response = Http::timeout(30)->withOptions(['verify' => false])->get($url);
                }

                if (!$response->successful()) {
                    $this->error("Failed HTTP {$response->status()}");
                    break;
                }

                $models = $this->parseListingPage($response->body());

                if (empty($models)) {
                    $emptyPages++;
                    if ($emptyPages >= 2) {
                        $this->info('2 consecutive empty pages, done.');
                        break;
                    }
                    $page++;
                    continue;
                }

                $emptyPages = 0;

                foreach ($models as $model) {
                    $downloadFile = $model['download_file'];

                    // Skip if already tracked
                    if (isset($allWsModels[$downloadFile])) continue;
                    $allWsModels[$downloadFile] = $model;

                    // Check if we have it
                    $inHistory = isset($history[$downloadFile]);

                    // Also check by stripping extension and matching local pk3s
                    $baseName = pathinfo($downloadFile, PATHINFO_FILENAME);
                    // ws uses "q3mdl-name.zip" format, strip "q3mdl-" prefix too
                    $baseNameClean = preg_replace('/^q3mdl-/', '', $baseName);
                    $inLocal = isset($localPk3s[$baseName]) || isset($localPk3s[$baseNameClean]);

                    if ($inHistory || $inLocal) {
                        $found++;
                    } else {
                        $missing[] = $model;
                    }
                }

                $this->info("  Found " . count($models) . " models | Total WS: " . count($allWsModels) . " | Missing so far: " . count($missing));

            } catch (\Exception $e) {
                $this->error("Error on page {$page}: " . $e->getMessage());
            }

            $page++;
            if ($sleepSeconds > 0) sleep($sleepSeconds);
        }

        // Save results
        $report = [
            'generated_at' => now()->toIso8601String(),
            'stats' => [
                'total_ws_models' => count($allWsModels),
                'found_locally' => $found,
                'missing' => count($missing),
                'pages_scraped' => $page,
            ],
            'missing' => $missing,
            'all_ws_models' => array_values($allWsModels),
        ];

        $reportFile = 'missing-models-' . date('Y-m-d_H-i-s') . '.json';
        Storage::disk('local')->put($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info("Report saved to: storage/app/{$reportFile}");
        $this->newLine();
        $this->info("╔══════════════════════════════════════╗");
        $this->info("║       MISSING MODELS SUMMARY         ║");
        $this->info("╠══════════════════════════════════════╣");
        $this->info("║  Total on ws.q3df.org:  " . count($allWsModels));
        $this->info("║  Found locally:         {$found}");
        $this->error("║  MISSING:              " . count($missing));
        $this->info("║  Pages scraped:         {$page}");
        $this->info("╚══════════════════════════════════════╝");

        if (!empty($missing)) {
            $this->newLine();
            $this->info("First 20 missing models:");
            foreach (array_slice($missing, 0, 20) as $m) {
                $this->warn("  {$m['name']} -> {$m['download_file']} ({$m['download_url']})");
            }
            if (count($missing) > 20) {
                $this->info("  ... and " . (count($missing) - 20) . " more (see JSON report)");
            }
        }

        return 0;
    }

    private function parseListingPage(string $html): array
    {
        $models = [];

        preg_match_all('/<div class="models_modelitem">.*?<\/div>/is', $html, $containers, PREG_SET_ORDER);

        foreach ($containers as $container) {
            $containerHtml = $container[0];

            if (!preg_match('/<a href="(\/model\/[^"]+)">([^<]+)<\/a>/', $containerHtml, $modelMatch)) {
                continue;
            }

            if (!preg_match('/<a href="(\/models\/downloads\/([^"]+\.(pk3|zip)))">/', $containerHtml, $downloadMatch)) {
                continue;
            }

            $models[] = [
                'name' => trim($modelMatch[2]),
                'detail_url' => 'https://ws.q3df.org' . $modelMatch[1],
                'download_url' => 'https://ws.q3df.org' . $downloadMatch[1],
                'download_file' => trim($downloadMatch[2]),
            ];
        }

        return $models;
    }
}
