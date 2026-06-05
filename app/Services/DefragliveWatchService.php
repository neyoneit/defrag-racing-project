<?php

namespace App\Services;

use App\Models\DefragliveContest;
use App\Models\DefragliveWatchSession;
use App\Models\OnlinePlayer;
use App\Models\Server;
use App\Models\User;
use App\Models\UserAlias;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Watch-time tracking + contest logic for DefragLive.
 *
 * The bot publishes a serverstate (~every 2.5s) carrying current_player - who
 * it is spectating right now. The server_state table only keeps "now", so we
 * accrue that history here into continuous watch sessions, then a contest is a
 * window over those sessions and the winner is a watch-time-weighted raffle.
 *
 * Identity: the bot payload carries no mdd_id (svinfo doesn't expose it), so we
 * resolve the watched player to a defrag account by the live OnlinePlayer map
 * (precise client_id match, then clean-name), then UserAlias, falling back to
 * the bare name (still tracked; payout handled manually).
 */
class DefragliveWatchService
{
    /**
     * Window (seconds) the public "now watching" indicator treats an open
     * session as live for. The bot emits serverstate only on change, so during
     * a quiet watch there are no updates - we still consider it live well past a
     * tick interval. Purely cosmetic (which session is "current"); it never
     * affects credited time.
     */
    public const LIVE_WINDOW = 600;

    /**
     * Safety net only: a session open longer than this with no update is treated
     * as an orphan from a crashed/stopped bot and closed at its last sighting,
     * so it doesn't credit unbounded time. Set well above any real single watch.
     */
    public const ORPHAN_HOURS = 6;

    /** Seconds of watch time per raffle ticket (1 minute = 1 ticket). */
    public const SECONDS_PER_TICKET = 60;

    /**
     * Fold one serverstate snapshot into the open watch session. Called from the
     * ingest serverstate branch (safe under concurrent POSTs via a row lock).
     *
     * Model: the bot is always spectating exactly one player. Silence just means
     * "still on the same player, nothing changed" - NOT a gap. So a watch runs
     * from when it started until the bot moves to someone else (or goes idle),
     * and that move is always reported by an emit. We credit the previous player
     * the full span up to that moment. No gap cap, no "offline" guessing.
     */
    public function accrue(array $serverstate): void
    {
        $current = $serverstate['current_player'] ?? null;

        // No one being spectated, or the bot is watching itself (idle) -> the
        // current watch stretch is over; close it and credit nobody.
        if (!is_array($current) || $this->isBot($current, $serverstate)) {
            $this->closeOpenSession();

            return;
        }

        $name = (string) ($current['n'] ?? '');
        $clean = $this->cleanName($name);
        if ($clean === '') {
            $this->closeOpenSession();

            return;
        }

        $ip = $serverstate['ip'] ?? null;
        $mapname = $serverstate['mapname'] ?? null;
        $identity = $this->resolve($current, $ip);
        $key = $this->keyFor($identity['mdd_id'], $clean);

        DB::transaction(function () use ($identity, $name, $clean, $ip, $mapname, $key) {
            $open = DefragliveWatchSession::open()->lockForUpdate()->first();

            if ($open) {
                $openKey = $this->keyFor($open->mdd_id, $open->player_name_clean);

                if ($openKey === $key) {
                    // Still the same player: extend the running span to now.
                    $open->seconds = $this->span($open->started_at, now());
                    $open->last_seen_at = now();
                    $open->mapname = $mapname ?: $open->mapname;
                    if (!$open->mdd_id && $identity['mdd_id']) {
                        $open->mdd_id = $identity['mdd_id'];
                        $open->user_id = $identity['user_id'];
                    }
                    $open->save();

                    return;
                }

                // Switched to a different player: the previous one was watched
                // continuously until this very moment, so credit the full span.
                $open->seconds = $this->span($open->started_at, now());
                $open->ended_at = now();
                $open->save();
            }

            $this->openSession($identity, $name, $clean, $ip, $mapname);
        });
    }

    /**
     * Close the currently open session (player switch reported as idle / bot
     * self-spectating). The watch ran continuously until now, so credit the
     * full span to now.
     */
    public function closeOpenSession(): void
    {
        DB::transaction(function () {
            $open = DefragliveWatchSession::open()->lockForUpdate()->first();
            if (!$open) {
                return;
            }

            $open->seconds = $this->span($open->started_at, now());
            $open->ended_at = now();
            $open->save();
        });
    }

    /**
     * Safety net for a crashed/stopped bot only: a session left open for hours
     * with no update is an orphan (the bot is gone, no switch ever came). Close
     * it at its last sighting so it can't credit unbounded time. Normal watches
     * are closed by the next switch/idle, long before this fires.
     */
    public function closeStaleSessions(): int
    {
        $stale = DefragliveWatchSession::open()
            ->where('last_seen_at', '<', now()->subHours(self::ORPHAN_HOURS))
            ->get();

        foreach ($stale as $s) {
            $end = $s->last_seen_at ?? $s->started_at;
            $s->seconds = $this->span($s->started_at, $end);
            $s->ended_at = $end;
            $s->save();
        }

        return $stale->count();
    }

    /** Whole seconds between two timestamps (>= 0). */
    private function span($start, $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        return max(0, (int) $start->diffInSeconds($end));
    }

    private function openSession(array $identity, string $name, string $clean, ?string $ip, ?string $mapname): void
    {
        DefragliveWatchSession::create([
            'mdd_id' => $identity['mdd_id'],
            'user_id' => $identity['user_id'],
            'player_name' => $name,
            'player_name_clean' => $clean,
            'ip' => $ip,
            'mapname' => $mapname,
            'seconds' => 0,
            'started_at' => now(),
            'last_seen_at' => now(),
            'ended_at' => null,
        ]);
    }

    /**
     * Resolve a watched player to [mdd_id, user_id]. Best effort, never throws.
     * Order: precise live client_id on this server -> clean-name OnlinePlayer ->
     * UserAlias -> unresolved (name only).
     */
    public function resolve(array $current, ?string $ip): array
    {
        $clean = $this->cleanName((string) ($current['n'] ?? ''));
        $clientId = isset($current['id']) ? (int) $current['id'] : null;
        $mddId = null;

        // 1) Precise: the same client_id on the server with this ip.
        if ($ip && $clientId !== null) {
            $serverId = Server::where('ip', $ip)->value('id');
            if ($serverId) {
                $op = OnlinePlayer::where('server_id', $serverId)
                    ->where('client_id', $clientId)
                    ->whereNotNull('mdd_id')
                    ->first();
                if ($op && $op->mdd_id) {
                    $mddId = (int) $op->mdd_id;
                }
            }
        }

        // 2) Clean-name match against the live player map.
        if (!$mddId && $clean !== '') {
            $op = OnlinePlayer::whereNotNull('mdd_id')
                ->get(['name', 'mdd_id'])
                ->first(fn ($p) => $this->cleanName((string) $p->name) === $clean);
            if ($op && $op->mdd_id) {
                $mddId = (int) $op->mdd_id;
            }
        }

        // 3) Approved alias history.
        if (!$mddId && $clean !== '') {
            $alias = UserAlias::query()
                ->whereNotNull('mdd_id')
                ->get(['mdd_id', 'alias'])
                ->first(fn ($a) => $this->cleanName((string) $a->alias) === $clean);
            if ($alias && $alias->mdd_id) {
                $mddId = (int) $alias->mdd_id;
            }
        }

        $userId = $mddId ? User::where('mdd_id', $mddId)->value('id') : null;

        return ['mdd_id' => $mddId, 'user_id' => $userId ? (int) $userId : null];
    }

    /**
     * Aggregate watch time per player over a contest window. Returns rows sorted
     * by total seconds desc: ['mdd_id','user_id','name','name_clean','seconds',
     * 'tickets','user']. $limit null = everyone (used by the draw).
     */
    public function leaderboard(DefragliveContest $contest, ?int $limit = 10): array
    {
        $rows = DefragliveWatchSession::query()
            ->where('started_at', '>=', $contest->starts_at)
            ->where('started_at', '<=', $contest->ends_at)
            ->orderBy('id')
            ->get(['mdd_id', 'user_id', 'player_name', 'player_name_clean', 'seconds', 'started_at', 'ended_at']);

        $groups = [];
        foreach ($rows as $r) {
            $key = $this->keyFor($r->mdd_id, $r->player_name_clean);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'mdd_id' => $r->mdd_id ? (int) $r->mdd_id : null,
                    'user_id' => $r->user_id ? (int) $r->user_id : null,
                    'name' => $r->player_name,
                    'name_clean' => $r->player_name_clean,
                    'seconds' => 0,
                ];
            }
            // Still-open session = currently being watched; count it live to now
            // (its stored seconds only updates on an emit, which may be silent).
            $groups[$key]['seconds'] += $r->ended_at
                ? (int) $r->seconds
                : $this->span($r->started_at, now());
            // Keep the latest seen colored name / resolved identity.
            $groups[$key]['name'] = $r->player_name ?: $groups[$key]['name'];
            if ($r->mdd_id) {
                $groups[$key]['mdd_id'] = (int) $r->mdd_id;
            }
            if ($r->user_id) {
                $groups[$key]['user_id'] = (int) $r->user_id;
            }
        }

        $entries = array_values($groups);
        usort($entries, fn ($a, $b) => $b['seconds'] <=> $a['seconds']);

        if ($limit !== null) {
            $entries = array_slice($entries, 0, $limit);
        }

        // Attach a lightweight resolved user for display / profile links.
        $userIds = array_values(array_filter(array_column($entries, 'user_id')));
        $users = $userIds
            ? User::whereIn('id', $userIds)
                ->get(['id', 'name', 'plain_name', 'profile_photo_path', 'country', 'mdd_id'])
                ->keyBy('id')
            : collect();

        foreach ($entries as &$e) {
            $e['tickets'] = intdiv($e['seconds'], self::SECONDS_PER_TICKET);
            $e['user'] = $e['user_id'] ? $users->get($e['user_id']) : null;
        }

        return $entries;
    }

    /**
     * Draw the contest winner via a watch-time-weighted raffle (1 ticket per
     * full minute watched). Persists winner + ticket transparency fields and
     * marks the contest closed. Returns the winning entry, or null if nobody
     * accrued at least one ticket.
     */
    public function draw(DefragliveContest $contest): ?array
    {
        $entries = array_values(array_filter(
            $this->leaderboard($contest, null),
            fn ($e) => $e['tickets'] >= 1
        ));

        $total = array_sum(array_column($entries, 'tickets'));
        if ($total < 1) {
            return null;
        }

        $winning = random_int(1, $total);
        $cursor = 0;
        $winner = null;
        foreach ($entries as $e) {
            $cursor += $e['tickets'];
            if ($winning <= $cursor) {
                $winner = $e;
                break;
            }
        }
        $winner = $winner ?? $entries[array_key_last($entries)];

        $contest->update([
            'winner_mdd_id' => $winner['mdd_id'],
            'winner_user_id' => $winner['user_id'],
            'winner_name' => $winner['name'],
            'winner_seconds' => $winner['seconds'],
            'winner_tickets' => $winner['tickets'],
            'total_tickets' => $total,
            'winning_ticket' => $winning,
            'drawn_at' => now(),
            'status' => DefragliveContest::STATUS_CLOSED,
        ]);

        return $winner;
    }

    /** Strip Quake 3 colour codes, collapse whitespace, lowercase: a stable key. */
    public function cleanName(string $name): string
    {
        $plain = preg_replace('/\^[0-9A-Za-z]/', '', $name);
        $plain = trim(preg_replace('/\s+/', ' ', $plain ?? ''));

        return mb_strtolower($plain);
    }

    private function keyFor($mddId, ?string $clean): string
    {
        return $mddId ? 'mdd:' . (int) $mddId : 'name:' . (string) $clean;
    }

    /** Is the "current player" actually the bot self-spectating (idle)? */
    private function isBot(array $current, array $serverstate): bool
    {
        $botId = $serverstate['bot_id'] ?? null;
        if ($botId !== null && isset($current['id']) && (int) $current['id'] === (int) $botId) {
            return true;
        }

        $name = mb_strtolower((string) ($current['n'] ?? ''));

        return str_contains($name, 'defrag.live') || str_contains($name, 'defraglive');
    }
}
