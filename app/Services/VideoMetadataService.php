<?php

namespace App\Services;

use App\Models\RenderedVideo;
use App\Models\Record;
use App\Services\ContentFilter;
use Illuminate\Support\Facades\DB;

class VideoMetadataService
{
    /**
     * Generate YouTube title for a rendered video.
     */
    public static function generateTitle(RenderedVideo $video): string
    {
        $prefixes = [];

        // World Record prefix
        if (self::isWorldRecord($video)) {
            $prefixes[] = 'World Record';
        }

        // Chip prefixes from filename
        $chips = self::parseChips($video->demo_filename);
        if (in_array('tool_assisted', $chips)) {
            $prefixes[] = 'TAS';
        }
        if (in_array('sv_cheats', $chips)) {
            $prefixes[] = 'CHEATS';
        }
        if (in_array('timescale', $chips)) {
            $prefixes[] = 'TAS';
        }

        $prefixes = array_unique($prefixes);
        $prefix = !empty($prefixes) ? '[' . implode('] [', $prefixes) . '] ' : '';

        $physics = strtoupper($video->physics ?? '');
        $time = self::formatTime($video->time_ms);
        $mapName = ContentFilter::filterText($video->map_name ?? 'Unknown');
        $playerName = self::cleanPlayerName($video->player_name ?? 'Unknown');

        return "{$prefix}{$mapName} | {$time} by {$playerName} ({$physics}) - Quake 3 DeFRaG";
    }

    /**
     * Generate YouTube description for a rendered video.
     */
    public static function generateDescription(RenderedVideo $video): string
    {
        $physics = strtoupper($video->physics ?? '');
        $rawMapName = $video->map_name ?? 'Unknown';
        $mapName = ContentFilter::filterText($rawMapName);
        $mapNameClean = ($mapName === $rawMapName); // no blocked terms in raw name
        $playerName = self::cleanPlayerName($video->player_name ?? 'Unknown');
        $time = self::formatTime($video->time_ms);

        $desc = "Nickname: {$playerName}\n";
        $desc .= "Time: {$time}\n";
        $desc .= "Physics: {$physics}\n";
        $desc .= "Map: {$mapName}\n";

        if (self::isWorldRecord($video)) {
            $desc .= "🏆 World Record\n";
        }

        $chips = self::parseChips($video->demo_filename);
        if (!empty($chips)) {
            $desc .= "Chips: " . implode(', ', $chips) . "\n";
        }

        $desc .= "\n";
        if ($video->demo_id) {
            $desc .= "Demo download: https://defrag.racing/demos/{$video->demo_id}/download\n";
        }
        // Skip the map-page URL when the map name itself contains a blocked
        // term — surfacing the raw name as a clickable URL would defeat the
        // censoring above.
        if ($mapNameClean) {
            $desc .= "Map page: https://defrag.racing/maps/{$rawMapName}\n";
        }
        $desc .= "Website: https://defrag.racing/\n";
        $desc .= "Discord: https://discord.defrag.racing/\n";
        $desc .= "\n";
        $desc .= "Quake 3 DeFRaG speedrun on {$mapName}. ";
        $desc .= "DeFRaG is a Quake III Arena modification focused on movement and trickjumping. ";
        $desc .= "Strafe jumping, rocket jumping, plasma climbing, circle jumping.\n";
        $desc .= self::tagsToHashtags(self::generateTags($video)) . "\n";

        return $desc;
    }

    /**
     * Convert tag array to space-separated hashtags.
     * Strips spaces and non-alphanumeric chars from each tag.
     */
    public static function tagsToHashtags(array $tags): string
    {
        return collect($tags)
            ->map(fn ($t) => '#' . preg_replace('/[^a-zA-Z0-9]/', '', $t))
            ->filter(fn ($t) => strlen($t) > 1)
            ->unique()
            ->implode(' ');
    }

    /**
     * Generate YouTube tags for a rendered video.
     */
    public static function generateTags(RenderedVideo $video): array
    {
        $tags = [
            'Quake 3', 'Quake III Arena', 'DeFRaG', 'defrag', 'speedrun',
            'trickjump', 'strafe jump', 'rocket jump', 'movement',
            'quake movement', 'q3 defrag', 'quake 3 defrag',
            'id Software', 'arena fps', 'FPS', 'gaming',
            'bunny hop', 'bhop',
        ];

        // Drop the raw map name from tags when it contains a blocked term;
        // YouTube tags can't be censored mid-string without confusing search.
        if ($video->map_name) {
            $cleanedMap = ContentFilter::filterText($video->map_name);
            if ($cleanedMap === $video->map_name) {
                $tags[] = $video->map_name;
            }
        }

        if ($video->physics) {
            $physics = strtolower($video->physics);
            $tags[] = $physics;
            if ($physics === 'cpm') {
                $tags[] = 'Challenge ProMode';
                $tags[] = 'CPMA';
            } elseif ($physics === 'vq3') {
                $tags[] = 'Vanilla Quake 3';
                $tags[] = 'VQ3';
            }
        }

        if ($video->player_name) {
            // cleanPlayerName routes through ContentFilter::filterAuthor,
            // so blocked nicks become 'UnnamedPlayer' here too.
            $cleanedNick = self::cleanPlayerName($video->player_name);
            if ($cleanedNick !== '' && $cleanedNick !== 'UnnamedPlayer') {
                $tags[] = $cleanedNick;
            }
        }

        if (self::isWorldRecord($video)) {
            $tags[] = 'world record';
            $tags[] = 'WR';
        }

        // Map-specific tags from map weapons/functions
        $mapInfo = DB::table('maps')
            ->where('name', $video->map_name)
            ->first(['weapons', 'functions']);

        if ($mapInfo) {
            $weapons = strtolower($mapInfo->weapons ?? '');
            $functions = strtolower($mapInfo->functions ?? '');

            if (str_contains($weapons, 'plasma')) {
                $tags[] = 'plasma';
                $tags[] = 'plasma climb';
                $tags[] = 'plasma climbing';
            }
            if (str_contains($weapons, 'rocket')) {
                $tags[] = 'rocket';
                $tags[] = 'rocket jump';
                $tags[] = 'rocket jumping';
            }
            if (str_contains($weapons, 'grenade')) {
                $tags[] = 'grenade';
                $tags[] = 'grenade jump';
            }
            if (str_contains($weapons, 'bfg')) {
                $tags[] = 'BFG';
                $tags[] = 'bfg jump';
            }
            if (str_contains($weapons, 'lg')) {
                $tags[] = 'lg';
                $tags[] = 'lightning gun';
            }
            if (str_contains($functions, 'slick')) {
                $tags[] = 'slick';
                $tags[] = 'ice movement';
                $tags[] = 'ice';
            }
            if (str_contains($functions, 'tele')) {
                $tags[] = 'tele';
                $tags[] = 'teleporter';
            }
            if (str_contains($functions, 'jumppad')) {
                $tags[] = 'jumppad';
                $tags[] = 'jump pad';
            }
        }

        $chips = self::parseChips($video->demo_filename);
        if (in_array('tool_assisted', $chips) || in_array('timescale', $chips)) {
            $tags[] = 'TAS';
            $tags[] = 'tool assisted speedrun';
        }

        return array_values(array_unique($tags));
    }

    /**
     * Check if a video is a World Record.
     */
    public static function isWorldRecord(RenderedVideo $video): bool
    {
        // Online: check record rank
        if ($video->record_id) {
            $rank = DB::table('records')
                ->where('id', $video->record_id)
                ->whereNull('deleted_at')
                ->value('rank');
            return $rank === 1;
        }

        // Offline: compare time with WR
        if ($video->time_ms && $video->map_name && $video->physics) {
            $wrTime = DB::table('records')
                ->where('mapname', $video->map_name)
                ->where('physics', $video->physics)
                ->where('rank', 1)
                ->whereNull('deleted_at')
                ->value('time');

            return $wrTime && $video->time_ms <= $wrTime;
        }

        return false;
    }

    /**
     * Parse chips from demo filename.
     */
    public static function parseChips(?string $filename): array
    {
        if (!$filename || !str_contains($filename, '{')) {
            return [];
        }

        $chips = [];
        if (preg_match('/\{([^}]+)\}/', $filename, $match)) {
            $chipStr = $match[1];
            if (str_contains($chipStr, 'tool_assisted')) $chips[] = 'tool_assisted';
            if (str_contains($chipStr, 'sv_cheats')) $chips[] = 'sv_cheats';
            if (str_contains($chipStr, 'timescale')) $chips[] = 'timescale';
        }

        return $chips;
    }

    /**
     * Strip Quake 3 colour codes AND replace the whole nickname with a
     * safe placeholder when it contains a blocked term. Used everywhere
     * the player name surfaces in YouTube metadata so a slur in someone's
     * nick can't strike the channel.
     */
    public static function cleanPlayerName(string $name): string
    {
        return ContentFilter::filterAuthor($name, 'UnnamedPlayer');
    }

    /**
     * Format time in ms to readable string.
     */
    public static function formatTime(?int $timeMs): string
    {
        if (!$timeMs) return '00.000';
        $m = floor($timeMs / 60000);
        $s = floor(($timeMs % 60000) / 1000);
        $ms = $timeMs % 1000;
        return sprintf('%02d.%02d.%03d', $m, $s, $ms);
    }

    /**
     * Get videos that need YouTube metadata update.
     * Returns videos where current title doesn't match generated title.
     */
    public static function getVideosNeedingUpdate(int $limit = 50): \Illuminate\Support\Collection
    {
        return RenderedVideo::where('status', 'completed')
            ->whereNotNull('youtube_video_id')
            ->whereNotNull('youtube_url')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($video) {
                return [
                    'id' => $video->id,
                    'youtube_video_id' => $video->youtube_video_id,
                    'title' => self::generateTitle($video),
                    'description' => self::generateDescription($video),
                    'tags' => self::generateTags($video),
                ];
            });
    }
}
