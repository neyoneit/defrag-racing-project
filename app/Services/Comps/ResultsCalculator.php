<?php

namespace App\Services\Comps;

use App\Models\CompResult;
use App\Models\CompRound;
use App\Models\CompSubmission;
use Illuminate\Support\Facades\DB;

/**
 * Freezes a finished round into standings, and scores them.
 *
 * The points table is fixed and does not move with turnout. Strongman derives
 * points from the size of the field, which works there because the field is
 * the same every event; here a round with four entrants would pay 4 for a win
 * and a round with twenty would pay 20, punishing a winner for a week nobody
 * showed up. Fixed, a win is 25 whoever turned up, and a small round simply
 * hands out the first few rows of the table.
 *
 * The size of the numbers is cosmetic - 50-36-30 orders identically to
 * 25-18-15. Only the shape matters, and this shape is steep at the top so that
 * winning beats turning up: on a flat 10-9-8-7 scale, five second places beat
 * three wins, which is not what a competition should say.
 */
class ResultsCalculator
{
    /**
     * Places 1 through 10. Everyone who finishes below gets
     * POINTS_FOR_FINISHING, which keeps tenth place worth something more than
     * eleventh and gives everyone else a reason to finish at all.
     */
    public const POINTS = [25, 18, 15, 12, 10, 8, 6, 4, 3, 2];

    public const POINTS_FOR_FINISHING = 1;

    /**
     * Points a single place is worth, before any sharing.
     */
    public function pointsForPlace(int $place): float
    {
        return (float) (self::POINTS[$place - 1] ?? self::POINTS_FOR_FINISHING);
    }

    /**
     * Write comp_results for both physics. Replaces whatever was there, so it
     * can be re-run after an entry is invalidated by a report.
     */
    public function freeze(CompRound $round): void
    {
        DB::transaction(function () use ($round) {
            $round->results()->delete();

            foreach (BallotResolver::PHYSICS as $physics) {
                foreach ($this->standings($round, $physics) as $row) {
                    CompResult::create([
                        'comp_round_id' => $round->id,
                        'physics' => $physics,
                        'user_id' => $row['user_id'],
                        'rank' => $row['rank'],
                        'time' => $row['time'],
                        'points' => $row['points'],
                    ]);
                }
            }
        });
    }

    /**
     * Ranked standings for one physics, ties sharing both rank and points.
     *
     * @return array<int, array{user_id:int, time:int, rank:int, points:float}>
     */
    public function standings(CompRound $round, string $physics): array
    {
        $best = $this->bestPerPlayer($round, $physics);

        if (empty($best)) {
            return [];
        }

        $out = [];
        $place = 1;

        // Equal times are one group: they share a rank, and they share the
        // points for every place the group occupies. Two people second take
        // (18 + 15) / 2 = 16.5 each and the next player is fourth, not third.
        foreach ($this->groupByTime($best) as $time => $userIds) {
            $size = count($userIds);

            $pot = 0.0;
            for ($i = 0; $i < $size; $i++) {
                $pot += $this->pointsForPlace($place + $i);
            }
            $share = round($pot / $size, 1);

            foreach ($userIds as $userId) {
                $out[] = [
                    'user_id' => $userId,
                    'time' => (int) $time,
                    'rank' => $place,
                    'points' => $share,
                ];
            }

            $place += $size;
        }

        return $out;
    }

    /**
     * Each player's best counting time. People upload repeatedly through a
     * round as they improve, and only the best of theirs is an entry.
     *
     * @return array<int, int>  user_id => time
     */
    private function bestPerPlayer(CompRound $round, string $physics): array
    {
        return CompSubmission::query()
            ->counting()
            ->where('comp_round_id', $round->id)
            ->where('physics', $physics)
            ->selectRaw('user_id, MIN(time) AS best')
            ->groupBy('user_id')
            ->pluck('best', 'user_id')
            ->map(fn ($t) => (int) $t)
            ->all();
    }

    /**
     * @param  array<int, int>  $best  user_id => time
     * @return array<int, array<int, int>>  time => [user_id, ...], fastest first
     */
    private function groupByTime(array $best): array
    {
        $groups = [];

        foreach ($best as $userId => $time) {
            $groups[$time][] = (int) $userId;
        }

        ksort($groups);

        return $groups;
    }
}
