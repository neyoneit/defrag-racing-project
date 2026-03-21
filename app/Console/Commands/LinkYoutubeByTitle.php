<?php

namespace App\Console\Commands;

use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use Illuminate\Console\Command;

class LinkYoutubeByTitle extends Command
{
    protected $signature = 'demos:link-youtube-title {file : Path to JSON file (no_demo_url.json or no_file_found.json)} {--dry-run : Preview without making changes}';
    protected $description = 'Link YouTube videos to demos by parsing title metadata (map, time, player)';

    public function handle(): int
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $entries = json_decode(file_get_contents($file), true);
        $this->info("Loaded " . count($entries) . " entries");

        if ($dryRun) {
            $this->warn("DRY RUN - no changes will be made");
        }

        $linkedByUrl = 0;
        $linkedByTitle = 0;
        $alreadyLinked = 0;
        $noMatch = 0;
        $noParse = 0;
        $multiMatch = 0;

        foreach ($entries as $entry) {
            $videoId = $entry['video_id'];
            $title = $entry['title'];

            // Skip if this YouTube video is already linked
            $existingByYt = RenderedVideo::where('youtube_video_id', $videoId)->first();
            if ($existingByYt) {
                $alreadyLinked++;
                continue;
            }

            // Method 1: defrag.racing URL in the entry
            if (!empty($entry['url']) && str_contains($entry['url'], 'defrag.racing')) {
                if (preg_match('/download-demo\/(\d+)/', $entry['url'], $m)) {
                    $demo = UploadedDemo::find((int)$m[1]);
                    if ($demo && !in_array($demo->status, ['failed', 'failed-validity', 'unsupported-version'])) {
                        // Check if demo already has a RenderedVideo
                        $existingForDemo = RenderedVideo::where('demo_id', $demo->id)->where('status', 'completed')->first();
                        if ($existingForDemo) {
                            $alreadyLinked++;
                            continue;
                        }

                        if ($dryRun) {
                            $timeStr = $this->formatTime($demo->time_ms);
                            $this->line("  URL LINK: demo#{$demo->id} ({$demo->map_name} | {$demo->player_name} | {$timeStr} | {$demo->physics}) -> yt={$videoId}");
                        } else {
                            $this->createRenderedVideo($demo, $videoId, $entry);
                        }
                        $linkedByUrl++;
                        continue;
                    }
                }
            }

            // Method 2: Parse title
            $parsed = $this->parseTitle($title);
            if (!$parsed) {
                $noParse++;
                continue;
            }

            $mapName = strtolower($parsed['map_name']);
            $timeMs = $parsed['time_ms'];
            $playerName = $parsed['player_name'];

            if (!$mapName || !$timeMs) {
                $noParse++;
                continue;
            }

            // Find matching demo
            $demos = UploadedDemo::where('map_name', $mapName)
                ->where('time_ms', $timeMs)
                ->whereNotIn('status', ['failed', 'failed-validity', 'unsupported-version'])
                ->get();

            // Filter by player name if multiple
            if ($demos->count() > 1) {
                $byName = $demos->filter(fn($d) => strtolower($d->player_name) === strtolower($playerName));
                if ($byName->count() >= 1) {
                    $demos = $byName;
                }
            }

            if ($demos->count() === 0) {
                $noMatch++;
                continue;
            }

            if ($demos->count() > 1) {
                $multiMatch++;
                continue;
            }

            $demo = $demos->first();

            // Check if demo already has a RenderedVideo
            $existingForDemo = RenderedVideo::where('demo_id', $demo->id)->where('status', 'completed')->first();
            if ($existingForDemo) {
                $alreadyLinked++;
                continue;
            }

            if ($dryRun) {
                $timeStr = $this->formatTime($demo->time_ms);
                $this->line("  TITLE LINK: demo#{$demo->id} ({$demo->map_name} | {$demo->player_name} | {$timeStr} | {$demo->physics}) -> yt={$videoId} [parsed: {$mapName} | {$playerName}]");
            } else {
                $this->createRenderedVideo($demo, $videoId, $entry);
            }
            $linkedByTitle++;
        }

        $this->info("\nResults:");
        $this->info("  Linked by URL: {$linkedByUrl}");
        $this->info("  Linked by title: {$linkedByTitle}");
        $this->info("  Already linked: {$alreadyLinked}");
        $this->info("  No match in DB: {$noMatch}");
        $this->info("  Could not parse title: {$noParse}");
        $this->info("  Multiple matches: {$multiMatch}");

        return 0;
    }

    private function parseTitle(string $title): ?array
    {
        // Format 1: "mapname | MM.SS.mmm by PlayerName (PHYSICS) - Quake 3 DeFRaG"
        if (preg_match('/^(.+?)\s*\|\s*(.+?)\s+by\s+(.+?)\s*\((\w+(?:\.\w+)?)\)\s*-/', $title, $m)) {
            return [
                'map_name' => trim($m[1]),
                'player_name' => trim($m[3]),
                'time_ms' => $this->timeToMs(trim($m[2])),
                'physics' => trim($m[4]),
            ];
        }

        // Format 2: "DeFRaG: PlayerName MM.SS.mmm physics mapname"
        if (preg_match('/^DeFRaG:\s+(.+?)\s+([\d:.]+)\s+(\w+)\s+(\S+)/', $title, $m)) {
            return [
                'player_name' => trim($m[1]),
                'time_ms' => $this->timeToMs(trim($m[2])),
                'physics' => trim($m[3]),
                'map_name' => trim($m[4]),
            ];
        }

        return null;
    }

    private function timeToMs(string $time): ?int
    {
        // MM.SS.mmm
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $time, $m)) {
            return (int)$m[1] * 60000 + (int)$m[2] * 1000 + (int)$m[3];
        }
        // M:SS.mmm
        if (preg_match('/^(\d+):(\d+)\.(\d+)$/', $time, $m)) {
            return (int)$m[1] * 60000 + (int)$m[2] * 1000 + (int)$m[3];
        }
        // SS.mmm (no minutes)
        if (preg_match('/^(\d+)\.(\d+)$/', $time, $m) && (int)$m[1] < 60) {
            return (int)$m[1] * 1000 + (int)$m[2];
        }
        return null;
    }

    private function formatTime(?int $timeMs): string
    {
        if (!$timeMs) return '?';
        return sprintf('%d:%02d.%03d', intdiv($timeMs, 60000), intdiv($timeMs % 60000, 1000), $timeMs % 1000);
    }

    private function createRenderedVideo(UploadedDemo $demo, string $youtubeVideoId, array $entry): void
    {
        RenderedVideo::create([
            'map_name' => $demo->map_name,
            'player_name' => $demo->player_name,
            'physics' => $demo->physics,
            'time_ms' => $demo->time_ms,
            'gametype' => $demo->gametype,
            'record_id' => $demo->record_id,
            'demo_id' => $demo->id,
            'source' => 'migration',
            'status' => 'completed',
            'priority' => 3,
            'demo_url' => "https://defrag.racing/demos/{$demo->id}/download",
            'youtube_url' => "https://www.youtube.com/watch?v={$youtubeVideoId}",
            'youtube_video_id' => $youtubeVideoId,
            'is_visible' => true,
            'published_at' => $entry['published_at'] ?? now(),
            'publish_approved' => true,
        ]);
    }
}
