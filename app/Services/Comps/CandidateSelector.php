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

    /**
     * How long a ballot is, and what each slot is for.
     *
     * Drawn at random the ballot mirrored the pool, and the pool is lopsided:
     * two maps in five are finished in under ten seconds and one in twenty
     * takes over forty-five. Half of all ballots came out with nothing longer
     * than thirty seconds on them and three quarters with nothing over a
     * minute, so "five random maps" meant, most weeks, five sprints.
     *
     * Each slot therefore draws from a band of world record time. Bands rather
     * than a spread of the map's own length because the record is the only
     * length we actually know, and the bounds are picked so no band is thin:
     * 2781, 3036, 4530, 3377 and 1237 maps as of August 2026. The smallest of
     * those lasts twenty years at one map a week.
     *
     * Nothing is barred for being short. A map finished in three seconds is
     * still somebody's favourite map, and a fifth of everything we have is
     * under five seconds; what they must not do is take four slots out of five,
     * which is what random drawing kept giving them.
     *
     * @var array<int, array{0: int, 1: int|null, 2: int}>  from, to (null = no ceiling), slots
     */
    public const TIME_BANDS = [
        [0, 10, 1],
        [10, 20, 2],
        [20, 45, 1],
        [45, null, 1],
    ];

    public const POOL_SIZE = 5;

    /**
     * Eligible sets already worked out this request, keyed by category and gun.
     *
     * Building one means grouping 800 000 records to find each map's fastest
     * time, which takes a couple of seconds. The admin page asks for all three
     * categories to show pool sizes and Livewire rebuilds it on every button
     * press, so without this the page cost seven seconds on each click.
     *
     * Per request only - the container hands out a fresh instance each time -
     * so a map tagged or played mid-session is picked up on the next load.
     *
     * @var array<string, array>
     */
    private array $memo = [];

    /** Every map with a record and its fastest time, built at most once. */
    private ?array $mapRows = null;

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
        $key = $category . '|' . ($weapon ?? '');

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

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
                // The fastest time anybody has on record, which is the only
                // measure of a map's length we actually hold. Used to spread
                // the ballot across TIME_BANDS.
                'wr_ms' => (int) $map->wr_ms,
                // Which physics this map cannot be finished in, so it goes on
                // one ballot and not the other. Null for almost everything.
                'blocked_physics' => $blocked[$map->id] ?? null,
            ];
        }

        return $this->memo[$key] = $out;
    }

    /**
     * The ballot itself: one map per slot in TIME_BANDS, so a week always has
     * a sprint and always has something long on it.
     *
     * A band that cannot fill its slots does not shrink the ballot. Lightning
     * has 32 maps in total and will empty a band eventually, and a short ballot
     * would be a worse answer than a slightly lopsided one - so the shortfall
     * is made up from whatever is left, nearest bands first. Asking for more
     * than the bands provide fills the remainder the same way.
     *
     * Returns fewer than $count only if the category has genuinely run dry,
     * which the caller should treat as a fault rather than quietly accept.
     */
    public function draw(string $category, ?string $weapon = null, int $count = self::POOL_SIZE): array
    {
        $pool = $this->eligible($category, $weapon);

        shuffle($pool);

        $banded = [];
        $taken = [];

        foreach ($this->slotsFor($count) as [$from, $to, $slots]) {
            $inBand = array_filter($pool, function ($map) use ($from, $to, $taken) {
                if (isset($taken[$map['id']])) {
                    return false;
                }

                $seconds = $map['wr_ms'] / 1000;

                return $seconds >= $from && ($to === null || $seconds < $to);
            });

            foreach (array_slice($inBand, 0, $slots) as $map) {
                $banded[] = $map;
                $taken[$map['id']] = true;
            }
        }

        // Short of the asked-for size, either because a band ran dry or
        // because the caller wanted a longer ballot than the bands describe.
        if (count($banded) < $count) {
            foreach ($pool as $map) {
                if (count($banded) >= $count) {
                    break;
                }

                if (! isset($taken[$map['id']])) {
                    $banded[] = $map;
                    $taken[$map['id']] = true;
                }
            }
        }

        // Shuffled again so the ballot is not presented shortest-first, which
        // would tell everybody which slot each map came out of.
        shuffle($banded);

        return array_slice($banded, 0, $count);
    }

    /**
     * TIME_BANDS scaled to a ballot of a given size.
     *
     * The bands describe a five map ballot, but the size is a setting and an
     * admin can ask for ten. Scaling keeps the shape - ten maps come out
     * 2/4/2/2 rather than the five banded maps plus five drawn at random,
     * which would let the short end take over again through the back door.
     *
     * Largest remainder, so a size the bands do not divide into is still split
     * as evenly as it can be: seven gives 2/3/1/1 rather than dropping the two
     * spare maps on whichever band happens to be first.
     *
     * @return array<int, array{0: int, 1: int|null, 2: int}>
     */
    private function slotsFor(int $count): array
    {
        $total = array_sum(array_column(self::TIME_BANDS, 2));

        if ($count === $total) {
            return self::TIME_BANDS;
        }

        $exact = [];
        $out = [];
        $used = 0;

        foreach (self::TIME_BANDS as $i => [$from, $to, $slots]) {
            $want = $slots / $total * $count;
            $whole = (int) floor($want);

            $out[$i] = [$from, $to, $whole];
            $exact[$i] = $want - $whole;
            $used += $whole;
        }

        // Hand the leftovers to the bands that lost the most in rounding.
        arsort($exact);

        foreach (array_keys($exact) as $i) {
            if ($used >= $count) {
                break;
            }

            $out[$i][2]++;
            $used++;
        }

        return $out;
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

    /**
     * Maps carrying at least one record, with the fastest of those times.
     *
     * A join rather than a whereExists, because the time is wanted and not only
     * the existence of one. The comparison stays in SQL either way, which
     * matters: `maps.name` has capitals on a few hundred maps where
     * `records.mapname` is lowercase, MySQL's collation ignores that, and the
     * same match written in PHP silently loses 35 of them.
     *
     * Held for the request rather than streamed. It is the expensive part -
     * grouping 800 000 records - and the three categories would otherwise pay
     * for it three times over on a page that shows all three pool sizes. The
     * result is fifteen thousand small rows, which costs nothing to keep.
     */
    private function mapsWithRecords(): array
    {
        if ($this->mapRows !== null) {
            return $this->mapRows;
        }

        $best = DB::table('records')
            ->select('mapname', DB::raw('MIN(time) AS wr_ms'))
            ->whereNull('deleted_at')
            ->where('time', '>', 0)
            ->groupBy('mapname');

        return $this->mapRows = DB::table('maps')
            ->select('maps.id', 'maps.name', 'maps.weapons', 'wr.wr_ms')
            ->joinSub($best, 'wr', fn ($join) => $join->on('wr.mapname', '=', 'maps.name'))
            ->get()
            ->all();
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
