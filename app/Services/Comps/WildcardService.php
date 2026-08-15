<?php

namespace App\Services\Comps;

use App\Models\Comp;
use App\Models\CompCandidate;
use App\Models\CompResult;
use App\Models\CompRound;
use App\Models\CompWildcard;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hands out the right to name a map, and spends it.
 *
 * Two ways to earn one. Winning a season gives you one outright, spendable on
 * a weekly - never on a season, so a champion cannot build the next season
 * underneath themselves. Winning five weeklies gives you one too; they need
 * not be consecutive, which is the point, because it rewards a year of showing
 * up rather than a hot month.
 */
class WildcardService
{
    /**
     * Award for a finished comp. Both physics have a winner, so both get a
     * right, and an equal-time tie at the top means two winners each holding
     * one. Idempotent - re-running a finished comp grants nothing twice.
     */
    public function awardFor(Comp $comp): int
    {
        $granted = 0;

        DB::transaction(function () use ($comp, &$granted) {
            $roundIds = $comp->rounds()->pluck('id');

            foreach (BallotResolver::PHYSICS as $physics) {
                foreach ($this->winnersOf($comp, $physics, $roundIds) as $userId) {
                    if ($comp->type === Comp::SEASON) {
                        $granted += $this->grantSeason($comp, $userId, $physics) ? 1 : 0;
                    }

                    $granted += $this->grantForWeeklyTally($userId, $physics) ? 1 : 0;
                }
            }
        });

        return $granted;
    }

    /**
     * Who won a comp in one physics. Weekly is the single round's rank 1;
     * season is the highest points total across its rounds. Both can return
     * more than one person, because equal times share a rank.
     *
     * @return array<int, int>  user ids
     */
    public function winnersOf(Comp $comp, string $physics, $roundIds = null): array
    {
        $roundIds = $roundIds ?? $comp->rounds()->pluck('id');

        if ($roundIds->isEmpty()) {
            return [];
        }

        if ($comp->isWeekly()) {
            return CompResult::whereIn('comp_round_id', $roundIds)
                ->where('physics', $physics)
                ->winners()
                ->pluck('user_id')
                ->all();
        }

        $totals = CompResult::whereIn('comp_round_id', $roundIds)
            ->where('physics', $physics)
            ->selectRaw('user_id, SUM(points) AS total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        if ($totals->isEmpty()) {
            return [];
        }

        $best = $totals->max();

        return $totals->filter(fn ($t) => (float) $t === (float) $best)->keys()->map(fn ($k) => (int) $k)->all();
    }

    private function grantSeason(Comp $comp, int $userId, string $physics): bool
    {
        $exists = CompWildcard::where('user_id', $userId)
            ->where('physics', $physics)
            ->where('source', CompWildcard::FROM_SEASON)
            ->where('source_comp_id', $comp->id)
            ->exists();

        if ($exists) {
            return false;
        }

        CompWildcard::create([
            'user_id' => $userId,
            'physics' => $physics,
            'source' => CompWildcard::FROM_SEASON,
            'source_comp_id' => $comp->id,
        ]);

        return true;
    }

    /**
     * One right per five weekly wins, counted over all time. Granting is
     * driven off the tally rather than off any single win, so it stays correct
     * whether wins come in a row or a year apart, and cannot double-grant.
     */
    private function grantForWeeklyTally(int $userId, string $physics): bool
    {
        $wins = CompResult::query()
            ->winners()
            ->where('physics', $physics)
            ->where('user_id', $userId)
            ->whereIn('comp_round_id', function ($q) {
                $q->select('comp_rounds.id')
                    ->from('comp_rounds')
                    ->join('comps', 'comps.id', '=', 'comp_rounds.comp_id')
                    ->where('comps.type', Comp::WEEKLY)
                    ->where('comps.status', 'finished');
            })
            ->count();

        $earned = intdiv($wins, CompWildcard::WEEKLY_WINS_REQUIRED);

        $already = CompWildcard::where('user_id', $userId)
            ->where('physics', $physics)
            ->where('source', CompWildcard::FROM_WEEKLIES)
            ->count();

        if ($earned <= $already) {
            return false;
        }

        CompWildcard::create([
            'user_id' => $userId,
            'physics' => $physics,
            'source' => CompWildcard::FROM_WEEKLIES,
        ]);

        return true;
    }

    /**
     * An unspent right this user holds, if any.
     *
     * Not filtered by physics: one is spendable on either ballot whatever it
     * was earned in. Oldest first, which matters to nobody today and will the
     * day somebody holds two.
     */
    public function heldBy(int $userId): ?CompWildcard
    {
        return CompWildcard::unused()
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Spend a right on a candidate. First one in takes the round; anyone else
     * holding one keeps it and is told the round is already decided.
     *
     * @throws RuntimeException when the round is not open, the map is not on
     *                          the ballot, or somebody got there first.
     */
    public function spend(CompWildcard $wildcard, CompRound $round, CompCandidate $candidate, string $physics): void
    {
        if ($wildcard->isSpent()) {
            throw new RuntimeException('This wildcard has already been used.');
        }

        if (! $round->isVoting()) {
            throw new RuntimeException('Voting for this round is closed.');
        }

        if ($candidate->comp_round_id !== $round->id) {
            throw new RuntimeException('That map is not on this ballot.');
        }

        if (! $candidate->votableIn($physics)) {
            throw new RuntimeException('That map cannot be finished in this physics.');
        }

        DB::transaction(function () use ($wildcard, $round, $candidate, $physics) {
            // Lock so two holders clicking together cannot both win the round.
            // Keyed on what a wildcard DECIDED rather than what earned it: the
            // two ballots are still settled separately, so one spent on CPM
            // leaves VQ3 open to the next holder.
            $taken = CompWildcard::where('used_on_round_id', $round->id)
                ->where('used_physics', $physics)
                ->whereNotNull('used_at')
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw new RuntimeException('Somebody has already used a wildcard on this round.');
            }

            $wildcard->update([
                'used_at' => now(),
                'used_physics' => $physics,
                'used_on_round_id' => $round->id,
                'used_map_id' => $candidate->map_id,
            ]);
        });
    }
}
