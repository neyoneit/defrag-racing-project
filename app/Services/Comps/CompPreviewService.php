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
                $best = $this->pick($forMap, $physics, $this->recordTime($map->name, $physics));

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

                $videos = $this->videosFor($map->name, $physics);

                // Something is already on its way. One render per map and
                // physics at a time, or a ballot could queue five of them
                // while the first is still going.
                if ($videos->whereIn('status', ['pending', 'processing'])->isNotEmpty()) {
                    continue;
                }

                $record = $this->recordTime($map->name, $physics);

                // A finished preview that is not the record is exactly what we
                // want, and there is nothing to do.
                $usable = $videos->first(
                    fn (RenderedVideo $v) => $v->status === 'completed'
                        && ($record === null || (int) $v->time_ms !== $record)
                );

                if ($usable) {
                    continue;
                }

                $demo = $this->pickDemo($map->name, $physics);

                // Either no demo at all, or the only run we hold is the one
                // already rendered - a second render of the same run would
                // produce the same video.
                if (! $demo || $videos->contains(fn (RenderedVideo $v) => (int) $v->demo_id === $demo->id
                    || (int) $v->time_ms === (int) $demo->time_ms)) {
                    continue;
                }

                $this->queueFor($demo, $map->name, $physics);
                $queued++;
            }
        }

        return $queued;
    }

    /**
     * Which video to show for one map and physics.
     *
     * Case-insensitively, and this is the whole reason a finished preview
     * could sit on YouTube and on the map page while the ballot showed
     * nothing. The render pipeline writes `CPM`; comps asks for `cpm`; a
     * collection filter compares strings exactly, so every video the site
     * already had was invisible here. The mode suffix goes too - a `CPM.TR`
     * render is still a CPM run of the map.
     *
     * A video that is NOT the record wins over one that is. An old render of
     * the world record is better than a black rectangle, so it is shown while
     * a middle-of-the-field one is being made - but the moment that arrives it
     * takes over. See queueMissing for why the record is the wrong preview.
     */
    private function pick(Collection $forMap, string $physics, ?int $recordTime): ?RenderedVideo
    {
        $ofPhysics = $forMap->filter(
            fn (RenderedVideo $v) => strtolower(strtok((string) $v->physics, '.')) === $physics
        );

        $completed = $ofPhysics->where('status', 'completed')->sortBy('time_ms');

        $notTheRecord = $recordTime === null
            ? $completed
            : $completed->filter(fn (RenderedVideo $v) => (int) $v->time_ms !== $recordTime);

        return $notTheRecord->first()
            ?? $completed->first()
            ?? $ofPhysics->sortBy('time_ms')->first();
    }

    /**
     * The fastest run we hold for this map and physics, in milliseconds.
     *
     * Read off the demos rather than the records table on purpose: a preview
     * can only ever be a demo we have, so "the record" here means the best of
     * what could be rendered. A faster time nobody uploaded a demo of cannot
     * become a preview and is not what we are trying to avoid showing.
     */
    private function recordTime(string $mapName, string $physics): ?int
    {
        $best = UploadedDemo::where('map_name', $mapName)
            ->where('physics', $physics)
            ->whereNotNull('time_ms')
            ->min('time_ms');

        return $best === null ? null : (int) $best;
    }

    /** Every render we hold for this map and physics, whatever its case. */
    private function videosFor(string $mapName, string $physics): Collection
    {
        return RenderedVideo::where('map_name', $mapName)
            ->whereIn('status', ['completed', 'pending', 'processing'])
            ->get()
            ->filter(fn (RenderedVideo $v) => strtolower(strtok((string) $v->physics, '.')) === $physics)
            ->values();
    }

    /**
     * The demo to render as a preview: the middle of the field.
     *
     * **Never the world record.** A preview is there to show somebody what a
     * map is, not to hand them the route that wins it: the record is the run
     * the ballot's voters are about to compete against, and publishing it as
     * the illustration sets the week's answer before the week starts. It also
     * flatters the map badly - a record run is a specialist's line, not what
     * the map plays like.
     *
     * So the median time we hold. Fast enough to be a clean run of the route,
     * slow enough to be somebody's ordinary attempt. With two demos it takes
     * the slower; with one there is nothing to choose.
     */
    private function pickDemo(string $mapName, string $physics): ?UploadedDemo
    {
        $demos = UploadedDemo::where('map_name', $mapName)
            ->where('physics', $physics)
            ->whereNotNull('time_ms')
            ->orderBy('time_ms')
            ->limit(200)
            ->get(['id', 'time_ms', 'player_name', 'gametype']);

        if ($demos->isEmpty()) {
            return null;
        }

        return $demos->count() > 1 ? $demos[intdiv($demos->count(), 2)] : $demos[0];
    }

    private function queueFor(UploadedDemo $demo, string $mapName, string $physics): void
    {
        RenderedVideo::create([
            'map_name' => $mapName,
            'player_name' => $demo->player_name,
            // Upper case, the way the render pipeline writes every other row.
            // Comps used to store its own requests lower case, which left them
            // invisible to anything filtering the table the usual way.
            'physics' => strtoupper($physics),
            'time_ms' => $demo->time_ms,
            'gametype' => $demo->gametype,
            'demo_id' => $demo->id,
            'source' => self::SOURCE,
            'status' => 'pending',
            'priority' => self::PRIORITY,
        ]);
    }
}
