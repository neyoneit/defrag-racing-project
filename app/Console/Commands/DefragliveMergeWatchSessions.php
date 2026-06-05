<?php

namespace App\Console\Commands;

use App\Models\DefragliveWatchSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill: the old accrual logic fragmented a single continuous watch
 * (the bot emits serverstate only on change, so long quiet watches arrived as a
 * burst of tiny zero/short sessions). Under the corrected model a player is
 * watched continuously until the bot switches to someone else - and that switch
 * is exactly the moment the NEXT different player's session opens.
 *
 * So we collapse each run of consecutive same-player rows into its first row,
 * crediting it from its own started_at up to the next different player's
 * started_at (the switch). The final run, if still open, is left live; if
 * closed, it keeps its own ended_at. Runs by a different player are never merged
 * and the gap between two players is never credited to either.
 *
 * Dry-run by default; pass --apply to actually rewrite + delete fragments.
 */
class DefragliveMergeWatchSessions extends Command
{
    protected $signature = 'defraglive:merge-watch-sessions {--apply : Actually write changes (default is dry-run)}';

    protected $description = 'Merge fragmented DefragLive watch sessions into one row per continuous watch';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $rows = DefragliveWatchSession::orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->info('No watch sessions, nothing to do.');

            return self::SUCCESS;
        }

        // Build runs of consecutive same-player rows (same merge key in id order).
        $runs = [];
        $cur = null;
        foreach ($rows as $s) {
            $key = $s->mdd_id ? 'mdd:' . (int) $s->mdd_id : 'name:' . $s->player_name_clean;
            if ($cur && $cur['key'] === $key) {
                $cur['rows'][] = $s;
            } else {
                if ($cur) {
                    $runs[] = $cur;
                }
                $cur = ['key' => $key, 'rows' => [$s]];
            }
        }
        if ($cur) {
            $runs[] = $cur;
        }

        $deleteIds = [];
        $updates = [];   // [survivor, endsAt|null, seconds, open]
        $printed = [];

        foreach ($runs as $i => $r) {
            /** @var DefragliveWatchSession $first */
            $first = $r['rows'][0];
            $last = end($r['rows']);
            $next = $runs[$i + 1] ?? null;

            // End of this watch = the moment the bot switched to the next player.
            // For the final run: keep it live if open, else use its own ending.
            if ($next) {
                $endsAt = $next['rows'][0]->started_at;
                $open = false;
            } else {
                $open = $last->ended_at === null;
                $endsAt = $open ? null : $last->ended_at;
            }

            $seconds = $open || !$endsAt
                ? 0
                : max(0, (int) $first->started_at->diffInSeconds($endsAt));

            $oldSum = array_sum(array_map(fn ($x) => (int) $x->seconds, $r['rows']));
            $fragIds = array_map(fn ($x) => $x->id, $r['rows']);

            // Carry forward the best map + resolved identity seen in the run.
            $mapname = null;
            $mddId = null;
            $userId = null;
            $lastSeen = $first->last_seen_at;
            foreach ($r['rows'] as $x) {
                $mapname = $x->mapname ?: $mapname;
                if ($x->mdd_id) {
                    $mddId = (int) $x->mdd_id;
                    $userId = $x->user_id ? (int) $x->user_id : $userId;
                }
                $lastSeen = $x->last_seen_at ?: $lastSeen;
            }

            $updates[] = compact('first', 'endsAt', 'seconds', 'open', 'mapname', 'mddId', 'userId', 'lastSeen');
            foreach (array_slice($fragIds, 1) as $id) {
                $deleteIds[] = $id;
            }

            $printed[] = [
                'keep' => $first->id,
                'player' => mb_substr($first->player_name_clean, 0, 20),
                'frags' => count($r['rows']),
                'from' => $first->started_at->format('m-d H:i:s'),
                'to' => $endsAt ? $endsAt->format('H:i:s') : 'LIVE',
                'old' => $oldSum,
                'new' => $open ? 'live' : $seconds,
                'drop' => implode(',', array_slice($fragIds, 1)) ?: '-',
            ];
        }

        $this->table(
            ['keep id', 'player', 'frags', 'from', 'to', 'old s', 'new s', 'delete ids'],
            array_map(fn ($p) => [
                $p['keep'], $p['player'], $p['frags'], $p['from'], $p['to'], $p['old'], $p['new'], $p['drop'],
            ], $printed)
        );

        $this->line(sprintf(
            'Rows now: %d  ->  runs: %d  (would delete %d fragment rows)',
            $rows->count(),
            count($runs),
            count($deleteIds)
        ));

        if (!$apply) {
            $this->warn('DRY RUN - nothing changed. Re-run with --apply to commit.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates, $deleteIds) {
            foreach ($updates as $u) {
                /** @var DefragliveWatchSession $s */
                $s = $u['first'];
                $s->ended_at = $u['endsAt'];
                $s->seconds = $u['seconds'];
                $s->last_seen_at = $u['lastSeen'];
                if ($u['mapname']) {
                    $s->mapname = $u['mapname'];
                }
                if ($u['mddId']) {
                    $s->mdd_id = $u['mddId'];
                    $s->user_id = $u['userId'];
                }
                $s->save();
            }

            if ($deleteIds) {
                DefragliveWatchSession::whereIn('id', $deleteIds)->delete();
            }
        });

        $this->info(sprintf('Applied: %d runs kept, %d fragment rows deleted.', count($updates), count($deleteIds)));

        return self::SUCCESS;
    }
}
