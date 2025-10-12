<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ScrapeQ3dfModels extends Command
{
    protected $signature = 'scrape:q3df-models {--limit=50 : Number of models to download}';
    protected $description = 'Download PK3 files from ws.q3df.org with author information';

    private $processedPk3Files = [];

    public function handle()
    {
        $this->info('Fetching models from ws.q3df.org...');

        try {
            // Fetch the models page
            $response = Http::get('https://ws.q3df.org/models/');

            if (!$response->successful()) {
                $this->error('Failed to fetch models page');
                return 1;
            }

            $html = $response->body();

            // Parse the HTML to extract model information
            $models = $this->parseModelsPage($html);

            $this->info('Found ' . count($models) . ' model entries');

            $limit = (int) $this->option('limit');
            $processed = 0;
            $downloaded = 0;

            // Create directory for downloaded PK3 files
            Storage::makeDirectory('bulk-upload');
            $authorsData = [];

            foreach ($models as $modelData) {
                if ($processed >= $limit) {
                    break;
                }

                // Skip if we already processed this PK3 file
                if (in_array($modelData['pk3_file'], $this->processedPk3Files)) {
                    $this->line("Skipping duplicate PK3: {$modelData['pk3_file']}");
                    continue;
                }

                $this->processedPk3Files[] = $modelData['pk3_file'];
                $processed++;

                $this->info("Downloading [{$processed}/{$limit}]: {$modelData['pk3_file']} by {$modelData['author']}");

                // Download the PK3 file
                try {
                    $result = $this->downloadPk3($modelData);

                    if ($result) {
                        $downloaded++;
                        // Store author info
                        $authorsData[$modelData['pk3_file']] = [
                            'author' => $modelData['author'],
                            'model_name' => $modelData['name'],
                        ];
                        $this->info("  ✓ Downloaded: {$modelData['pk3_file']}");
                    } else {
                        $this->error("  ✗ Failed to download: {$modelData['pk3_file']}");
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Error: " . $e->getMessage());
                }
            }

            // Save authors mapping to JSON file
            Storage::put('bulk-upload/authors.json', json_encode($authorsData, JSON_PRETTY_PRINT));

            $this->info("\nDownload complete!");
            $this->info("Processed: {$processed} unique PK3 files");
            $this->info("Successfully downloaded: {$downloaded} files");
            $this->info("PK3 files saved to: storage/app/bulk-upload/");
            $this->info("Authors mapping saved to: storage/app/bulk-upload/authors.json");

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function parseModelsPage($html)
    {
        $models = [];

        // Parse the HTML table rows
        // The format is: <a href="path/to/model.pk3">modelname.pk3</a> - Author Name
        preg_match_all('/<tr>.*?<td.*?>.*?<a href="([^"]+\.pk3)"[^>]*>([^<]+)<\/a>.*?<\/td>.*?<td.*?>(.*?)<\/td>.*?<\/tr>/s', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $pk3Url = trim($match[1]);
            $pk3File = trim($match[2]);
            $author = trim(strip_tags($match[3]));

            // Extract model name from PK3 file
            // e.g., "model-anarki.pk3" -> "anarki"
            $modelName = preg_replace('/^model-/', '', pathinfo($pk3File, PATHINFO_FILENAME));
            $modelName = preg_replace('/[^a-zA-Z0-9_-]/', '', $modelName);

            // Build full URL
            if (!str_starts_with($pk3Url, 'http')) {
                $pk3Url = 'https://ws.q3df.org/models/' . ltrim($pk3Url, '/');
            }

            $models[] = [
                'name' => $modelName,
                'author' => $author ?: 'Unknown',
                'pk3_file' => $pk3File,
                'pk3_url' => $pk3Url,
            ];
        }

        return $models;
    }

    private function downloadPk3($modelData)
    {
        // Download the PK3 file
        $response = Http::timeout(60)->get($modelData['pk3_url']);

        if (!$response->successful()) {
            throw new \Exception("Failed to download PK3 file");
        }

        // Save to storage/app/bulk-upload/
        $savePath = 'bulk-upload/' . $modelData['pk3_file'];
        Storage::put($savePath, $response->body());

        return true;
    }
}
