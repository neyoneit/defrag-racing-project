<?php

namespace App\Services\Comps;

use App\Models\CompCandidate;
use App\Models\CompMapReport;
use App\Models\Map;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Turns an upheld "this map cannot be finished in X" report into a permanent
 * fact about the map.
 *
 * The tags are `cpmonly` and `vq3only` and they are new. The existing `cpm` and
 * `vq3` tags do NOT mean this - plenty of maps carry both at once (cpmjump,
 * tatmt-s4, pornstar-slopin), so they describe a flavour rather than an
 * exclusion, and reusing them would quietly bar a hundred perfectly playable
 * maps from one physics.
 */
class MapEligibilityTagger
{
    /** Tag name that marks a map as playable ONLY in the given physics. */
    public const ONLY_TAG = [
        'cpm' => 'vq3only',
        'vq3' => 'cpmonly',
    ];

    /**
     * @param  string  $physics  the physics the map CANNOT be finished in
     */
    public function markImpossible(Map $map, string $physics, ?int $byUserId = null): void
    {
        $tagName = self::ONLY_TAG[$physics] ?? null;

        if (! $tagName) {
            return;
        }

        DB::transaction(function () use ($map, $physics, $tagName, $byUserId) {
            $tag = Tag::firstOrCreate(
                ['name' => $tagName],
                [
                    'display_name' => $tagName === 'cpmonly' ? 'CPM only' : 'VQ3 only',
                    'category' => 'physics',
                    'note' => 'Cannot be finished in the other physics. Keeps the map out of that physics\' comps pool.',
                ]
            );

            if (! $map->tags()->where('tags.id', $tag->id)->exists()) {
                $map->tags()->attach($tag->id, ['user_id' => $byUserId]);
                $tag->increment('usage_count');
            }

            // Take it off any ballot it is currently sitting on, so the change
            // is visible in the round that prompted the report rather than only
            // in future ones.
            CompCandidate::where('map_id', $map->id)
                ->whereHas('round', fn ($q) => $q->where('status', 'voting'))
                ->update(['blocked_physics' => $physics]);
        });
    }

    /**
     * Which physics a map is barred from, if any. Read straight off the tags so
     * there is one source of truth and the pool query and the ballot agree.
     */
    public function blockedPhysicsFor(Map $map): ?string
    {
        $names = $map->tags->pluck('name')->map(fn ($n) => strtolower($n));

        if ($names->contains('cpmonly')) {
            return 'vq3';
        }

        if ($names->contains('vq3only')) {
            return 'cpm';
        }

        return null;
    }

    /** Approve a report and apply it. */
    public function approve(CompMapReport $report, ?int $byUserId = null): void
    {
        if (! $report->map) {
            return;
        }

        $this->markImpossible($report->map, $report->physics, $byUserId);

        // Every open report for the same map and physics is settled by this
        // one decision - they were all saying the same thing.
        CompMapReport::where('map_id', $report->map_id)
            ->where('physics', $report->physics)
            ->where('status', 'open')
            ->update(['status' => 'approved']);
    }
}
