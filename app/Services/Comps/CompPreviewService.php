<?php

namespace App\Services\Comps;

use App\Models\CompRound;
use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use Illuminate\Support\Collection;

/**
 * Preview videos for the maps on a ballot.
 *
 * Somebody voting on five maps they may never have seen deserves to watch a
 * run of each first. There is already a rendering pipeline that turns demos
 * into YouTube videos, so this only has to ask it for what is missing and read
 * back what has arrived.
 *
 * A render needs a demo to render. A map nobody has ever uploaded a demo for
 * cannot get a preview from anywhere, and that is simply reported as no video
 * rather than queued to fail.
 */
class CompPreviewService
{
    /** Queue position. Lower goes first, and 0 is what a person waiting gets. */
    public const PRIORITY = 0;

    /** So these are tellable apart from launcher and web requests in the queue. */
    public const SOURCE = 'comps';

    /**
     * Videos for every candidate, keyed by map id then physics.
     *
     * @return array<int, array<string, array{status:string, youtube_url:?string, video_id:?string}>>
     */
    public function forRound(CompRound $round): array
    {
        $round->loadMissing('candidates.map');

        $names = $round->candidates->pluck('map.name')->filter()->all();

        if (empty($names)) {
            return [];
        }

        $videos = RenderedVideo::whereIn('map_name', $names)
            ->whereIn('status', ['completed', 'pending', 'processing'])
            ->get()
            ->groupBy('map_name');

        $out = [];

        foreach ($round->candidates as $candidate) {
            $map = $candidate->map;

            if (! $map) {
                continue;
            }

            $forMap = $videos->get($map->name, new Collection());

            foreach (BallotResolver::PHYSICS as $physics) {
                $best = $this->pick($forMap, $physics);

                $out[$candidate->map_id][$physics] = [
                    'status' => $best?->status ?? 'none',
                    'youtube_url' => $best?->youtube_url,
                    'video_id' => $best?->youtube_video_id,
                ];
            }
        }

        return $out;
    }

    /**
     * Ask for whatever is missing. Called when a ballot opens, which leaves a
     * week before anybody needs it - and, more to the point, a day between the
     * ballot closing and the map being played.
     *
     * @return int  how many renders were queued
     */
    public function queueMissing(CompRound $round): int
    {
        $round->loadMissing('candidates.map');

        $queued = 0;

        foreach ($round->candidates as $candidate) {
            $map = $candidate->map;

            if (! $map) {
                continue;
            }

            foreach (BallotResolver::PHYSICS as $physics) {
                // A map that cannot be finished in this physics has no run to
                // show and is not on that ballot anyway.
                if (! $candidate->votableIn($physics)) {
                    continue;
                }

                if ($this->hasOrIsGetting($map->name, $physics)) {
                    continue;
                }

                if ($this->queueOne($map->name, $physics)) {
                    $queued++;
                }
            }
        }

        return $queued;
    }

    private function pick(Collection $forMap, string $physics): ?RenderedVideo
    {
        $ofPhysics = $forMap->where('physics', $physics);

        // A finished video beats one still rendering, and the fastest run of
        // those is the one worth showing.
        return $ofPhysics->where('status', 'completed')->sortBy('time_ms')->first()
            ?? $ofPhysics->sortBy('time_ms')->first();
    }

    private function hasOrIsGetting(string $mapName, string $physics): bool
    {
        return RenderedVideo::where('map_name', $mapName)
            ->where('physics', $physics)
            ->whereIn('status', ['completed', 'pending', 'processing'])
            ->exists();
    }

    /**
     * Queue the fastest demo we hold for this map and physics. Returns false
     * when there is no demo to render, which is not an error - plenty of maps
     * have never had one uploaded.
     */
    private function queueOne(string $mapName, string $physics): bool
    {
        $demo = UploadedDemo::where('map_name', $mapName)
            ->where('physics', $physics)
            ->whereNotNull('time_ms')
            ->orderBy('time_ms')
            ->first();

        if (! $demo) {
            return false;
        }

        RenderedVideo::create([
            'map_name' => $mapName,
            'player_name' => $demo->player_name,
            'physics' => $physics,
            'time_ms' => $demo->time_ms,
            'gametype' => $demo->gametype,
            'demo_id' => $demo->id,
            'source' => self::SOURCE,
            'status' => 'pending',
            'priority' => self::PRIORITY,
        ]);

        return true;
    }
}
