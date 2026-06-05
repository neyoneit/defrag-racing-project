<?php

namespace App\Http\Controllers;

use App\Models\DefragliveWatchSession;
use App\Models\Map;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Public map log: what the DefragLive bot has been on, map by map - which map
 * from when to when, and which players it spectated there (with how long each).
 *
 * Built from the same watch sessions as the contest: a "map block" is a maximal
 * run of consecutive sessions sharing one mapname (the bot stays on a server/map
 * and spectates various players, then moves on). The still-open session keeps
 * its block live. It only ever reflects players the bot actually watched, not
 * the full server roster - that's all the history we keep.
 */
class DefragliveMapLogController extends Controller
{
    public function index()
    {
        // Most recent sessions, oldest-first so we can group in order, then we
        // present the blocks newest-first.
        $sessions = DefragliveWatchSession::query()
            ->orderByDesc('id')
            ->limit(800)
            ->get(['id', 'mdd_id', 'user_id', 'player_name', 'player_name_clean', 'mapname', 'seconds', 'started_at', 'ended_at'])
            ->reverse()
            ->values();

        $blocks = [];
        $cur = null;
        foreach ($sessions as $s) {
            $map = $s->mapname ?: 'unknown';

            if (!$cur || $cur['map'] !== $map) {
                if ($cur) {
                    $blocks[] = $cur;
                }
                $cur = [
                    'map' => $map,
                    'started_at' => $s->started_at,
                    'ended_at' => $s->ended_at,
                    'open' => $s->ended_at === null,
                    'players' => [],
                ];
            }

            // Extend the block to this session's end (live if it's still open).
            $cur['ended_at'] = $s->ended_at;
            $cur['open'] = $s->ended_at === null;

            $secs = $s->ended_at
                ? (int) $s->seconds
                : max(0, (int) $s->started_at->diffInSeconds(now()));

            $key = $s->mdd_id ? 'mdd:' . (int) $s->mdd_id : 'name:' . $s->player_name_clean;
            if (!isset($cur['players'][$key])) {
                $cur['players'][$key] = [
                    'name' => $s->player_name,
                    'mdd_id' => $s->mdd_id ? (int) $s->mdd_id : null,
                    'user_id' => $s->user_id ? (int) $s->user_id : null,
                    'seconds' => 0,
                ];
            }
            $cur['players'][$key]['seconds'] += $secs;
            $cur['players'][$key]['name'] = $s->player_name ?: $cur['players'][$key]['name'];
            if ($s->user_id) {
                $cur['players'][$key]['user_id'] = (int) $s->user_id;
            }
        }
        if ($cur) {
            $blocks[] = $cur;
        }

        // Newest first.
        $blocks = array_reverse($blocks);

        // Batch-resolve map thumbnails and player users (no N+1).
        $thumbs = Map::whereIn('name', collect($blocks)->pluck('map')->unique()->values())
            ->pluck('thumbnail', 'name');

        $userIds = collect($blocks)
            ->flatMap(fn ($b) => array_column($b['players'], 'user_id'))
            ->filter()->unique()->values();
        $users = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds)
                ->get(['id', 'name', 'plain_name', 'profile_photo_path', 'country'])
                ->keyBy('id')
            : collect();

        $payload = array_map(function (array $b) use ($thumbs, $users) {
            $players = array_values($b['players']);
            usort($players, fn ($a, $c) => $c['seconds'] <=> $a['seconds']);

            return [
                'map' => $b['map'],
                'map_thumbnail' => $thumbs[$b['map']] ?? null,
                'started_at' => optional($b['started_at'])->toIso8601String(),
                'ended_at' => $b['open'] ? null : optional($b['ended_at'])->toIso8601String(),
                'live' => $b['open'],
                'duration' => $b['open']
                    ? null
                    : max(0, (int) optional($b['started_at'])->diffInSeconds($b['ended_at'] ?? $b['started_at'])),
                'players' => array_map(fn ($p) => [
                    'name' => $p['name'],
                    'seconds' => $p['seconds'],
                    'user' => $p['user_id'] && $users->has($p['user_id']) ? [
                        'id' => $users[$p['user_id']]->id,
                        'name' => $users[$p['user_id']]->plain_name ?: $users[$p['user_id']]->name,
                        'profile_photo_path' => $users[$p['user_id']]->profile_photo_path,
                        'country' => $users[$p['user_id']]->country,
                    ] : null,
                ], $players),
            ];
        }, $blocks);

        return Inertia::render('DefragliveMapLog', [
            'blocks' => $payload,
        ]);
    }
}
