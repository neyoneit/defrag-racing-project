<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Reads the DeFRaG mod release listing off q3defrag.org.
 *
 * The site serves a plain Apache directory index, so the releases are parsed
 * out of the listing markup rather than an API.
 */
class DefragReleaseScraper
{
    public const STABLE_URL = 'https://q3defrag.org/files/defrag/';
    public const BETA_URL = 'https://q3defrag.org/files/defrag/beta/';

    /**
     * @return array<int, array{filename: string, date: string, size: string, version: string, url: string}>
     *         Oldest first.
     */
    public function scrape(string $url): array
    {
        $response = Http::withOptions(['verify' => false])->timeout(15)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} from {$url}");
        }

        $files = [];

        preg_match_all(
            '/<a href="(defrag_[\d.]+\.zip)">[^<]+<\/a>\s+([\d]+-\w+-[\d]+\s+[\d:]+)\s+(\d+)/i',
            $response->body(),
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $files[] = [
                'filename' => $match[1],
                'date' => trim($match[2]),
                'size' => trim($match[3]),
                'version' => $this->extractVersion($match[1]),
                'url' => $url . $match[1],
            ];
        }

        usort($files, fn ($a, $b) => version_compare($a['version'], $b['version']));

        return $files;
    }

    public function stable(): array
    {
        return $this->scrape(self::STABLE_URL);
    }

    public function beta(): array
    {
        return $this->scrape(self::BETA_URL);
    }

    public function extractVersion(string $filename): string
    {
        if (preg_match('/defrag_([\d.]+)\.zip/', $filename, $m)) {
            return $m[1];
        }

        return '0.0.0';
    }
}
