<?php

namespace App\Console\Commands;

use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use Illuminate\Console\Command;

class LinkYoutubeVideos extends Command
{
    protected $signature = 'demos:link-youtube {file : Path to md5_youtube_mapping.json} {--dry-run : Preview without making changes}';
    protected $description = 'Link YouTube videos to demos by MD5 hash matching';

    public function handle(): int
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $mapping = json_decode(file_get_contents($file), true);
        $this->info("Loaded " . count($mapping) . " MD5->YouTube mappings");

        if ($dryRun) {
            $this->warn("DRY RUN - no changes will be made");
        }

        $linked = 0;
        $alreadyLinked = 0;
        $noDemo = 0;
        $skipped = 0;

        foreach ($mapping as $md5 => $videoInfo) {
            $demo = UploadedDemo::where('file_hash', $md5)
                ->whereNotIn('status', ['failed', 'failed-validity', 'unsupported-version'])
                ->first();

            if (!$demo) {
                $noDemo++;
                continue;
            }

            $youtubeVideoId = $videoInfo['youtube_video_id'];
            $youtubeUrl = "https://www.youtube.com/watch?v={$youtubeVideoId}";

            // Check if this demo already has a RenderedVideo
            $existing = RenderedVideo::where('demo_id', $demo->id)
                ->where('status', 'completed')
                ->first();

            if ($existing) {
                $alreadyLinked++;
                continue;
            }

            // Check if this YouTube video is already linked to another demo
            $existingByYt = RenderedVideo::where('youtube_video_id', $youtubeVideoId)->first();
            if ($existingByYt) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  WOULD LINK: demo#{$demo->id} ({$demo->map_name} | {$demo->player_name}) -> yt={$youtubeVideoId}");
                $linked++;
                continue;
            }

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
                'youtube_url' => $youtubeUrl,
                'youtube_video_id' => $youtubeVideoId,
                'is_visible' => true,
                'published_at' => $videoInfo['published_at'] ?? now(),
                'publish_approved' => true,
            ]);

            $linked++;

            if ($linked % 100 === 0) {
                $this->info("  Linked {$linked} so far...");
            }
        }

        $this->info("\nResults:");
        $this->info("  Linked: {$linked}");
        $this->info("  Already linked: {$alreadyLinked}");
        $this->info("  No demo in DB: {$noDemo}");
        $this->info("  YouTube already used: {$skipped}");

        return 0;
    }
}
