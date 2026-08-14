<?php

namespace App\Services\Comps;

use App\Models\CompCandidate;
use App\Models\CompRound;
use App\Models\CompRoundMap;
use App\Models\CompWildcard;
use Illuminate\Support\Facades\DB;

/**
 * Turns a closed ballot into the map a round is played on.
 *
 * Four ways a map can be decided, in this order of precedence:
 *
 *   wildcard - somebody spent the right. First to spend it takes the round;
 *              the votes stay on record and simply stop deciding anything.
 *   vote     - most votes on that physics' ballot.
 *   carried  - that physics cast no votes at all, so it takes the map the
 *              other physics chose. Drawing at random next to a map people
 *              actually picked would be worse.
 *   random   - nobody voted in either physics. Nothing to carry.
 */
class BallotResolver
{
    public const PHYSICS = ['cpm', 'vq3'];

    /**
     * Decide both physics and write comp_round_maps. Safe to call twice: a
     * physics already decided is left alone.
     */
    public function resolve(CompRound $round): void
    {
        $round->loadMissing('candidates', 'maps');

        DB::transaction(function () use ($round) {
            $decided = [];

            // Wildcards first - they outrank the count outright.
            foreach (self::PHYSICS as $physics) {
                if ($round->maps->firstWhere('physics', $physics)) {
                    continue;
                }

                $spent = CompWildcard::where('used_on_round_id', $round->id)
                    ->where('physics', $physics)
                    ->whereNotNull('used_at')
                    ->whereNotNull('used_map_id')
                    ->orderBy('used_at')
                    ->first();

                if ($spent) {
                    $decided[$physics] = $this->write($round, $physics, $spent->used_map_id, 'wildcard');
                }
            }

            // Then the count.
            foreach (self::PHYSICS as $physics) {
                if (isset($decided[$physics]) || $round->maps->firstWhere('physics', $physics)) {
                    continue;
                }

                $winner = $this->winnerByVotes($round, $physics);

                if ($winner) {
                    $decided[$physics] = $this->write($round, $physics, $winner->map_id, 'vote');
                }
            }

            // A physics with no votes takes the other's map.
            foreach (self::PHYSICS as $physics) {
                if (isset($decided[$physics]) || $round->maps->firstWhere('physics', $physics)) {
                    continue;
                }

                $other = collect($decided)->first();

                if ($other) {
                    $decided[$physics] = $this->write($round, $physics, $other->map_id, 'carried');
                }
            }

            // Nobody voted at all.
            foreach (self::PHYSICS as $physics) {
                if (isset($decided[$physics]) || $round->maps->firstWhere('physics', $physics)) {
                    continue;
                }

                $candidate = $round->candidates
                    ->filter(fn (CompCandidate $c) => $c->votableIn($physics))
                    ->random();

                if ($candidate) {
                    $decided[$physics] = $this->write($round, $physics, $candidate->map_id, 'random');
                }
            }
        });

        $round->load('maps');
    }

    /**
     * Most votes on this ballot, then two tiebreaks in order:
     *
     *   1. more votes across both physics added together, even if this map
     *      lost the other ballot;
     *   2. reached that count first.
     */
    private function winnerByVotes(CompRound $round, string $physics): ?CompCandidate
    {
        $running = $round->candidates
            ->filter(fn (CompCandidate $c) => $c->votableIn($physics))
            ->filter(fn (CompCandidate $c) => $c->votesIn($physics) > 0);

        if ($running->isEmpty()) {
            return null;
        }

        $top = $running->max(fn (CompCandidate $c) => $c->votesIn($physics));
        $tied = $running->filter(fn (CompCandidate $c) => $c->votesIn($physics) === $top);

        if ($tied->count() === 1) {
            return $tied->first();
        }

        $topCombined = $tied->max(fn (CompCandidate $c) => $c->votesCombined());
        $tied = $tied->filter(fn (CompCandidate $c) => $c->votesCombined() === $topCombined);

        if ($tied->count() === 1) {
            return $tied->first();
        }

        return $tied
            ->sortBy(fn (CompCandidate $c) => $this->reachedCountAt($c, $physics, $top))
            ->first();
    }

    /**
     * When this candidate's Nth vote in this physics landed. Ordering by it
     * puts the map that got there first in front.
     */
    private function reachedCountAt(CompCandidate $candidate, string $physics, int $count): string
    {
        $at = DB::table('comp_votes')
            ->where('comp_candidate_id', $candidate->id)
            ->where('physics', $physics)
            ->orderBy('created_at')
            ->orderBy('id')
            ->skip(max($count - 1, 0))
            ->limit(1)
            ->value('created_at');

        // No timestamp means no such vote, which cannot happen for a count we
        // just read off the same rows - but sorting must not blow up if it
        // ever does, so such a candidate sorts last.
        return $at ?? '9999-12-31 23:59:59';
    }

    private function write(CompRound $round, string $physics, int $mapId, string $decidedBy): CompRoundMap
    {
        return CompRoundMap::create([
            'comp_round_id' => $round->id,
            'physics' => $physics,
            'map_id' => $mapId,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
        ]);
    }
}
