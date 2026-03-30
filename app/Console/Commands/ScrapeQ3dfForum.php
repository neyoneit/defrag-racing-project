<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScrapeQ3dfForum extends Command
{
    protected $signature = 'scrape:q3df-forum {--limit=50 : Max pages to download per run} {--delay=3 : Seconds between requests}';
    protected $description = 'Scrape q3df.org forum from Wayback Machine archive and save locally';

    private const STORAGE_PATH = 'q3df-forum';
    private const CDX_API = 'https://web.archive.org/cdx/search/cdx';
    private const WAYBACK_PREFIX = 'https://web.archive.org/web/';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');

        $this->info("Q3DF Forum Archiver - limit: {$limit}, delay: {$delay}s");

        // Step 1: Load or fetch CDX index
        $cdxFile = self::STORAGE_PATH . '/cdx_all.json';
        if (Storage::exists($cdxFile)) {
            $urls = json_decode(Storage::get($cdxFile), true);
            $this->info("Loaded " . count($urls) . " URLs from cache");
        } else {
            $this->info("Fetching CDX index from Wayback Machine...");
            $urls = $this->fetchCdxIndex();
            Storage::put($cdxFile, json_encode($urls, JSON_PRETTY_PRINT));
            $this->info("Cached " . count($urls) . " URLs");
        }

        // Step 2: Download pages we don't have yet
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($urls as $entry) {
            if ($downloaded >= $limit) {
                $this->info("Reached limit of {$limit} downloads");
                break;
            }

            $filename = $this->urlToFilename($entry['url']);
            $storagePath = self::STORAGE_PATH . '/pages/' . $filename;

            if (Storage::exists($storagePath)) {
                $skipped++;
                continue;
            }

            $waybackUrl = self::WAYBACK_PREFIX . $entry['timestamp'] . '/' . $entry['url'];

            try {
                $response = Http::withOptions(['verify' => false])
                    ->timeout(20)
                    ->get($waybackUrl);

                if ($response->successful() && strlen($response->body()) > 1000) {
                    Storage::put($storagePath, $response->body());
                    $downloaded++;
                    $this->line("  [{$downloaded}/{$limit}] Saved: {$filename} (" . strlen($response->body()) . " bytes)");
                } else {
                    $failed++;
                    $this->warn("  Failed: {$filename} (HTTP {$response->status()}, " . strlen($response->body()) . " bytes)");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->warn("  Error: {$filename} - " . $e->getMessage());
            }

            sleep($delay);
        }

        $this->info("Done. Downloaded: {$downloaded}, Skipped (exists): {$skipped}, Failed: {$failed}");
        $this->info("Total archived pages: " . count(Storage::files(self::STORAGE_PATH . '/pages')));

        return 0;
    }

    private function fetchCdxIndex(): array
    {
        $urls = [];

        // Fetch all viewtopic URLs
        $this->line("  Fetching viewtopic URLs...");
        $response = Http::timeout(30)->get(self::CDX_API, [
            'url' => 'q3df.org/forum/viewtopic.php*',
            'output' => 'json',
            'fl' => 'timestamp,original,statuscode',
            'collapse' => 'urlkey',
            'filter' => 'statuscode:200',
            'limit' => 2000,
        ]);

        if ($response->successful()) {
            $rows = $response->json();
            // Skip header row
            array_shift($rows);
            foreach ($rows as $row) {
                $urls[] = ['timestamp' => $row[0], 'url' => $row[1], 'type' => 'topic'];
            }
        }

        // Fetch all viewforum URLs
        $this->line("  Fetching viewforum URLs...");
        $response = Http::timeout(30)->get(self::CDX_API, [
            'url' => 'q3df.org/forum/viewforum.php*',
            'output' => 'json',
            'fl' => 'timestamp,original,statuscode',
            'collapse' => 'urlkey',
            'filter' => 'statuscode:200',
            'limit' => 500,
        ]);

        if ($response->successful()) {
            $rows = $response->json();
            array_shift($rows);
            foreach ($rows as $row) {
                $urls[] = ['timestamp' => $row[0], 'url' => $row[1], 'type' => 'forum'];
            }
        }

        // Fetch index page
        $urls[] = ['timestamp' => '20240417', 'url' => 'https://q3df.org/forum/index.php', 'type' => 'index'];

        $this->line("  Total: " . count($urls) . " URLs");
        return $urls;
    }

    private function urlToFilename(string $url): string
    {
        // Convert URL to a safe filename
        $path = parse_url($url, PHP_URL_QUERY) ?? parse_url($url, PHP_URL_PATH) ?? 'unknown';
        $safe = preg_replace('/[^a-zA-Z0-9_=&.-]/', '_', $path);
        return substr($safe, 0, 200) . '.html';
    }
}
