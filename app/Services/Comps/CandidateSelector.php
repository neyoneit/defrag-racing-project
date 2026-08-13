<?php

namespace App\Services\Comps;

use Illuminate\Support\Facades\DB;

/**
 * Draws the maps that go on a round's ballot.
 *
 * A map qualifies when it has at least one record, sits in the round's
 * category, and has never been played in a comps before. That last one is
 * permanent rather than a cooldown: strafe comes up three weeks in five, about
 * 156 rounds a year against 7 300 strafe maps, and weapon and combo about ten
 * a year each. The supply outlasts anybody reading this.
 *
 * Only a map that was actually *played* is burned. Losing a ballot costs a map
 * nothing and it can be drawn again.
 */
class CandidateSelector
{
    /**
     * Weekly runs one map a week, so the three categories take turns. Spread
     * rather than blocked - `strafe, strafe, strafe, weapon, combo` keeps the
     * same 3:1:1 ratio but would hand out three strafe weeks in a row, and
     * weapon and combo would then not appear for a month at a time.
     */
    public const WEEKLY_CYCLE = [
        MapClassifier::STRAFE,
        MapClassifier::WEAPON,
        MapClassifier::STRAFE,
        MapClassifier::COMBO,
        MapClassifier::STRAFE,
    ];

    public const POOL_SIZE = 5;

    public function __construct(private MapClassifier $classifier)
    {
    }

    /**
     * Which category a weekly falls in, from its sequential number.
     */
    public function categoryForWeekly(int $number): string
    {
        return self::WEEKLY_CYCLE[($number - 1) % count(self::WEEKLY_CYCLE)];
    }

    /**
     * Every map that could go on a ballot in this category, as
     * [['id' => .., 'name' => .., 'weapon' => ..], ...].
     *
     * The category cannot be expressed in SQL - it comes out of parsing a
     * comma-separated column against a tag - so the eligible set is narrowed in
     * the query and classified in PHP. That is a pass over roughly fifteen
     * thousand rows, which is nothing for something drawn once a week.
     *
     * @param  string|null  $weapon  restrict a weapon category to this one gun
     */
    public function eligible(string $category, ?string $weapon = null): array
    {
        $strafeTagged = $this->strafeTaggedMapIds();
        $played = $this->playedMapIds();
        $blocked = $this->blockedPhysicsMapIds();

        $out = [];

        foreach ($this->mapsWithRecords() as $map) {
            if (isset($played[$map->id])) {
                continue;
            }

            $verdict = $this->classifier->classify($map->weapons, isset($strafeTagged[$map->id]));

            if ($verdict['category'] !== $category) {
                continue;
            }

            if ($weapon !== null && $verdict['weapon'] !== $weapon) {
                continue;
            }

            $out[] = [
                'id' => $map->id,
                'name' => $map->name,
                'weapon' => $verdict['weapon'],
                // Which physics this map cannot be finished in, so it goes on
                // one ballot and not the other. Null for almost everything.
                'blocked_physics' => $blocked[$map->id] ?? null,
            ];
        }

        return $out;
    }

    /**
     * The ballot itself. Returns fewer than POOL_SIZE only if the category has
     * genuinely run dry, which the caller should treat as a fault rather than
     * quietly accept.
     */
    public function draw(string $category, ?string $weapon = null, int $count = self::POOL_SIZE): array
    {
        $pool = $this->eligible($category, $weapon);

        shuffle($pool);

        return array_slice($pool, 0, $count);
    }

    /**
     * Which gun a weapon round runs. Drawn evenly across the five rather than
     * weighted by how many maps each has, so lightning gets its turn: weighted
     * by supply it would essentially never come up against rocket's 1 900 maps.
     * Evenly, a weapon round lands on lightning about once in five, a weapon
     * round comes about once in five weeks, and there are 32 lightning maps -
     * so they last decades.
     */
    public function drawWeapon(): string
    {
        return MapClassifier::COUNTED[array_rand(MapClassifier::COUNTED)];
    }

    /** Maps carrying at least one record, keyed by nothing - a lazy cursor. */
    private function mapsWithRecords(): \Generator
    {
        $query = DB::table('maps')
            ->select('maps.id', 'maps.name', 'maps.weapons')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('records')
                    ->whereColumn('records.mapname', 'maps.name');
            });

        foreach ($query->cursor() as $row) {
            yield $row;
        }
    }

    private function strafeTaggedMapIds(): array
    {
        return DB::table('map_tag')
            ->join('tags', 'tags.id', '=', 'map_tag.tag_id')
            ->where('tags.name', 'strafe')
            ->pluck('map_tag.map_id')
            ->flip()
            ->all();
    }

    /**
     * Maps barred from one physics, as map_id => the physics they are barred
     * from. `cpmonly` bars vq3 and the other way round.
     *
     * Note these are NOT the existing `cpm` and `vq3` tags, which mean
     * something else entirely - a good few maps carry both at once.
     *
     * @return array<int, string>
     */
    private function blockedPhysicsMapIds(): array
    {
        $rows = DB::table('map_tag')
            ->join('tags', 'tags.id', '=', 'map_tag.tag_id')
            ->whereIn('tags.name', array_values(MapEligibilityTagger::ONLY_TAG))
            ->select('map_tag.map_id', 'tags.name')
            ->get();

        $flip = array_flip(MapEligibilityTagger::ONLY_TAG);

        $out = [];

        foreach ($rows as $row) {
            $out[$row->map_id] = $flip[strtolower($row->name)] ?? null;
        }

        return array_filter($out);
    }

    /**
     * Maps already played in a comps. Read from the winning maps, so a map that
     * only ever lost a ballot is not in here.
     */
    private function playedMapIds(): array
    {
        return DB::table('comp_round_maps')
            ->distinct()
            ->pluck('map_id')
            ->flip()
            ->all();
    }
}
