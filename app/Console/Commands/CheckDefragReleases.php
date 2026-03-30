<?php

namespace App\Console\Commands;

use App\Models\WikiPage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckDefragReleases extends Command
{
    protected $signature = 'wiki:check-defrag-releases';
    protected $description = 'Check q3defrag.org for new DeFRaG mod releases and update the wiki page';

    private const DEFRAG_URL = 'https://q3defrag.org/files/defrag/';
    private const BETA_URL = 'https://q3defrag.org/files/defrag/beta/';
    private const WIKI_SLUG = 'defrag-releases';

    public function handle(): int
    {
        $this->info('Checking q3defrag.org for releases...');

        try {
            $stableFiles = $this->scrapeDirectory(self::DEFRAG_URL);
            $betaFiles = $this->scrapeDirectory(self::BETA_URL);
        } catch (\Exception $e) {
            $this->error('Failed to fetch: ' . $e->getMessage());
            Log::warning('CheckDefragReleases: Failed to fetch q3defrag.org - ' . $e->getMessage());
            return 1;
        }

        if (empty($stableFiles)) {
            $this->warn('No files found - site may be down.');
            return 1;
        }

        $page = WikiPage::where('slug', self::WIKI_SLUG)->first();
        $oldContent = $page ? $page->content : '';

        $newContent = $this->buildPageContent($stableFiles, $betaFiles);

        if (!$page) {
            $page = WikiPage::create([
                'slug' => self::WIKI_SLUG,
                'title' => 'DeFRaG Mod Releases',
                'content' => $newContent,
                'parent_id' => null,
                'sort_order' => 0,
                'is_locked' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ]);
            $this->info("Created wiki page: {$page->id}");
            Log::info('CheckDefragReleases: Created wiki page with ' . count($stableFiles) . ' stable + ' . count($betaFiles) . ' beta releases.');
            return 0;
        }

        // Check if content changed (new file detected)
        $oldFileCount = substr_count($oldContent, 'defrag_1.');
        $newFileCount = substr_count($newContent, 'defrag_1.');

        if ($newFileCount > $oldFileCount) {
            $page->content = $newContent;
            $page->save();
            $this->info("Wiki updated! New releases detected ({$oldFileCount} -> {$newFileCount} entries).");
            Log::info("CheckDefragReleases: New releases detected ({$oldFileCount} -> {$newFileCount}). Wiki updated.");
        } else {
            $this->info("No new releases. ({$newFileCount} entries, unchanged)");
        }

        return 0;
    }

    private function scrapeDirectory(string $url): array
    {
        $response = Http::withOptions(['verify' => false])->timeout(15)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} from {$url}");
        }

        $html = $response->body();
        $files = [];

        // Parse Apache/nginx directory listing: <a href="filename">filename</a> date size
        preg_match_all(
            '/<a href="(defrag_[\d.]+\.zip)">[^<]+<\/a>\s+([\d]+-\w+-[\d]+\s+[\d:]+)\s+(\d+)/i',
            $html,
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

        // Sort by version
        usort($files, fn($a, $b) => version_compare($a['version'], $b['version']));

        return $files;
    }

    private function extractVersion(string $filename): string
    {
        if (preg_match('/defrag_([\d.]+)\.zip/', $filename, $m)) {
            return $m[1];
        }
        return '0.0.0';
    }

    private function formatSize(string $sizeStr): string
    {
        $bytes = (int) $sizeStr;
        if ($bytes > 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes > 1024) {
            return round($bytes / 1024, 0) . ' KB';
        }
        return $sizeStr;
    }

    private function buildPageContent(array $stableFiles, array $betaFiles): string
    {
        $h2 = 'font-size: 1.5rem; font-weight: 700; color: #e2e8f0; margin: 2.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(59, 130, 246, 0.4);';
        $h3 = 'font-size: 1.2rem; font-weight: 600; color: #cbd5e1; margin: 1.8rem 0 0.8rem; padding-left: 0.75rem; border-left: 3px solid rgba(59, 130, 246, 0.5);';
        $p = 'color: #9ca3af; line-height: 1.7; margin-bottom: 1rem;';
        $s = 'color: #e2e8f0; font-weight: 600;';
        $c = 'background: rgba(15, 23, 42, 0.6); color: #67e8f9; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.875em;';
        $ths = 'padding: 0.6rem 0.8rem; text-align: left; color: #e2e8f0; border-bottom: 2px solid rgba(59, 130, 246, 0.4); font-weight: 600; font-size: 0.85em;';
        $tds = 'padding: 0.5rem 0.8rem; color: #9ca3af; border-bottom: 1px solid rgba(55, 65, 81, 0.3); font-size: 0.875em;';

        $latest = end($stableFiles);
        $latestVersion = $latest ? $latest['version'] : 'unknown';
        $latestDate = $latest ? $latest['date'] : '';

        $content = "<h2 style=\"{$h2}\">DeFRaG Mod Releases</h2>\n";
        $content .= "<p style=\"{$p}\">Official DeFRaG mod releases from <a href=\"https://q3defrag.org/files/defrag/\" style=\"color: #60a5fa;\">q3defrag.org</a>. This page is <strong style=\"{$s}\">automatically updated weekly</strong>.</p>\n";
        $content .= "<p style=\"{$p}\"><strong style=\"{$s}\">Latest stable version:</strong> <code style=\"{$c}\">{$latestVersion}</code> ({$latestDate})</p>\n";

        // Stable releases table (newest first)
        $content .= "<h2 style=\"{$h2}\">Stable Releases</h2>\n";
        $content .= "<table style=\"width: 100%; border-collapse: collapse; margin: 1rem 0;\">\n";
        $content .= "<thead><tr style=\"background: rgba(30, 41, 59, 0.8);\">";
        $content .= "<th style=\"{$ths}\">Version</th><th style=\"{$ths}\">Date</th><th style=\"{$ths}\">Size</th><th style=\"{$ths}\">Download</th>";
        $content .= "</tr></thead><tbody>\n";

        $reversed = array_reverse($stableFiles);
        $rowIdx = 0;
        foreach ($reversed as $file) {
            $bg = $rowIdx % 2 === 0 ? 'rgba(15, 23, 42, 0.3)' : 'rgba(15, 23, 42, 0.5)';
            $sizeFormatted = $this->formatSize($file['size']);
            $isLatest = ($file['version'] === $latestVersion) ? ' <span style="background: #22c55e; color: #000; padding: 0.1rem 0.4rem; border-radius: 0.25rem; font-size: 0.75em; font-weight: 600;">LATEST</span>' : '';
            $content .= "<tr style=\"background: {$bg};\">";
            $content .= "<td style=\"{$tds}\"><strong style=\"{$s}\">{$file['version']}</strong>{$isLatest}</td>";
            $content .= "<td style=\"{$tds}\">{$file['date']}</td>";
            $content .= "<td style=\"{$tds}\">{$sizeFormatted}</td>";
            $content .= "<td style=\"{$tds}\"><a href=\"{$file['url']}\" style=\"color: #60a5fa;\">{$file['filename']}</a></td>";
            $content .= "</tr>\n";
            $rowIdx++;
        }
        $content .= "</tbody></table>\n";

        // Beta releases
        if (!empty($betaFiles)) {
            $content .= "<h2 style=\"{$h2}\">Beta / Unstable Releases</h2>\n";
            $content .= "<p style=\"{$p}\">These are experimental builds with new features that may not be stable. Not recommended for competitive play.</p>\n";
            $content .= "<table style=\"width: 100%; border-collapse: collapse; margin: 1rem 0;\">\n";
            $content .= "<thead><tr style=\"background: rgba(30, 41, 59, 0.8);\">";
            $content .= "<th style=\"{$ths}\">Version</th><th style=\"{$ths}\">Date</th><th style=\"{$ths}\">Size</th><th style=\"{$ths}\">Download</th>";
            $content .= "</tr></thead><tbody>\n";

            $rowIdx = 0;
            foreach (array_reverse($betaFiles) as $file) {
                $bg = $rowIdx % 2 === 0 ? 'rgba(15, 23, 42, 0.3)' : 'rgba(15, 23, 42, 0.5)';
                $sizeFormatted = $this->formatSize($file['size']);
                $content .= "<tr style=\"background: {$bg};\">";
                $content .= "<td style=\"{$tds}\"><strong style=\"{$s}\">{$file['version']}</strong></td>";
                $content .= "<td style=\"{$tds}\">{$file['date']}</td>";
                $content .= "<td style=\"{$tds}\">{$sizeFormatted}</td>";
                $content .= "<td style=\"{$tds}\"><a href=\"{$file['url']}\" style=\"color: #60a5fa;\">{$file['filename']}</a></td>";
                $content .= "</tr>\n";
                $rowIdx++;
            }
            $content .= "</tbody></table>\n";
        }

        // Notes
        $content .= "<h2 style=\"{$h2}\">Installation</h2>\n";
        $content .= "<p style=\"{$p}\">Download the latest .zip file and extract the <code style=\"{$c}\">defrag</code> folder into your Quake III Arena directory (next to <code style=\"{$c}\">baseq3</code>, NOT inside it). See the <a href=\"/wiki/installation\" style=\"color: #60a5fa;\">Installation &amp; Setup</a> guide for detailed instructions.</p>\n";
        $content .= "<p style=\"{$p}\"><strong style=\"{$s}\">Recommended for competitive play:</strong> Versions 1.91.23 through 1.91.31 are accepted on leaderboards.</p>\n";

        // Credits
        $content .= '<hr style="border: none; border-top: 1px solid rgba(55, 65, 81, 0.5); margin: 3rem 0 1.5rem;">';
        $content .= '<div style="padding: 1rem 1.25rem; background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(55, 65, 81, 0.3); border-radius: 0.5rem; font-size: 0.8em; color: #64748b; line-height: 1.6;">';
        $content .= '<strong style="color: #94a3b8;">Sources &amp; Credits</strong><br>';
        $content .= 'Data sourced from <a href="https://q3defrag.org/files/defrag/" style="color: #60a5fa;">q3defrag.org</a> (official DeFRaG distribution). This page is automatically updated every Sunday.<br>';
        $content .= 'Last checked: ' . now()->format('Y-m-d H:i') . ' UTC';
        $content .= '</div>';

        return $content;
    }
}
