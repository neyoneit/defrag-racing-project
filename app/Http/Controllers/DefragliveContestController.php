<?php

namespace App\Http\Controllers;

use App\Models\DefragliveContest;
use App\Models\DefragliveWatchSession;
use App\Services\DefragliveWatchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public DefragLive watch-time contest page: the live leaderboard of the most-
 * spectated players this period, the prize/countdown, the viewer's own odds,
 * and past winners. The winner is a watch-time-weighted raffle, so the page
 * shows tickets + odds, not just a ranking.
 */
class DefragliveContestController extends Controller
{
    public function index(Request $request, DefragliveWatchService $service)
    {
        $contest = DefragliveContest::current()->first();

        $leaderboard = [];
        $totalTickets = 0;
        $myEntry = null;

        if ($contest) {
            $all = $service->leaderboard($contest, null);
            $totalTickets = array_sum(array_map(
                fn ($e) => $e['tickets'] >= 1 ? $e['tickets'] : 0,
                $all
            ));

            $userId = auth()->id();
            $mddId = auth()->user()?->mdd_id;

            foreach ($all as $i => $e) {
                if (($userId && $e['user_id'] === $userId)
                    || ($mddId && $e['mdd_id'] === (int) $mddId)) {
                    $myEntry = [
                        'rank' => $i + 1,
                        'seconds' => $e['seconds'],
                        'tickets' => $e['tickets'],
                        'odds' => $totalTickets > 0 ? round($e['tickets'] / $totalTickets * 100, 1) : 0,
                    ];
                    break;
                }
            }

            $leaderboard = array_map($this->present(...), array_slice($all, 0, 10));
        }

        $open = DefragliveWatchSession::open()
            ->where('started_at', '>=', now()->subHours(DefragliveWatchService::ORPHAN_HOURS))
            ->orderByDesc('id')
            ->first();

        return Inertia::render('DefragliveContest', [
            'contest' => $contest ? [
                'id' => $contest->id,
                'title' => $contest->title,
                'prize_amount' => $contest->prize_amount,
                'prize_currency' => $contest->prize_currency,
                'starts_at' => $contest->starts_at?->toIso8601String(),
                'ends_at' => $contest->ends_at?->toIso8601String(),
            ] : null,
            'leaderboard' => $leaderboard,
            'totalTickets' => $totalTickets,
            'myEntry' => $myEntry,
            'nowWatching' => $open ? [
                'name' => $open->player_name,
                'mapname' => $open->mapname,
                'map_thumbnail' => $open->mapname
                    ? \App\Models\Map::where('name', $open->mapname)->value('thumbnail')
                    : null,
            ] : null,
            'pastWinners' => DefragliveContest::whereNotNull('winner_name')
                ->orderByDesc('drawn_at')
                ->limit(10)
                ->get()
                ->map(fn (DefragliveContest $c) => [
                    'title' => $c->title,
                    'winner_name' => $c->winner_name,
                    'winner_user_id' => $c->winner_user_id,
                    'prize_amount' => $c->prize_amount,
                    'prize_currency' => $c->prize_currency,
                    'drawn_at' => $c->drawn_at?->toDateString(),
                    'status' => $c->status,
                ]),
            'hallOfFame' => $this->hallOfFame($service),
        ]);
    }

    /**
     * All-time winners: every drawn contest grouped by winner, ranked by number
     * of wins then total prize won. A small hall of fame under the page.
     */
    private function hallOfFame(DefragliveWatchService $service): array
    {
        $won = DefragliveContest::whereNotNull('winner_name')
            ->get(['winner_user_id', 'winner_mdd_id', 'winner_name', 'prize_amount', 'prize_currency']);

        $groups = [];
        foreach ($won as $c) {
            $key = $c->winner_user_id
                ? 'u:' . $c->winner_user_id
                : 'n:' . $service->cleanName((string) $c->winner_name);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'winner_user_id' => $c->winner_user_id,
                    'name' => $c->winner_name,
                    'wins' => 0,
                    'total' => 0.0,
                    'currency' => $c->prize_currency,
                ];
            }
            $groups[$key]['wins']++;
            $groups[$key]['total'] += (float) $c->prize_amount;
            $groups[$key]['name'] = $c->winner_name ?: $groups[$key]['name'];
        }

        $entries = array_values($groups);
        usort($entries, fn ($a, $b) => [$b['wins'], $b['total']] <=> [$a['wins'], $a['total']]);
        $entries = array_slice($entries, 0, 10);

        $userIds = array_values(array_filter(array_column($entries, 'winner_user_id')));
        $users = $userIds
            ? \App\Models\User::whereIn('id', $userIds)
                ->get(['id', 'name', 'profile_photo_path', 'country'])
                ->keyBy('id')
            : collect();

        foreach ($entries as &$e) {
            $u = $e['winner_user_id'] ? $users->get($e['winner_user_id']) : null;
            $e['user'] = $u ? [
                'id' => $u->id,
                'profile_photo_path' => $u->profile_photo_path,
                'country' => $u->country,
            ] : null;
        }

        return $entries;
    }

    /** Shape one leaderboard entry for the page (colored name + resolved user). */
    private function present(array $e): array
    {
        return [
            'name' => $e['name'],
            'seconds' => $e['seconds'],
            'tickets' => $e['tickets'],
            'mdd_id' => $e['mdd_id'],
            'user' => $e['user'] ? [
                'id' => $e['user']->id,
                'name' => $e['user']->name,
                'profile_photo_path' => $e['user']->profile_photo_path,
                'country' => $e['user']->country,
            ] : null,
        ];
    }
}
