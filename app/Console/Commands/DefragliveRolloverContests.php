<?php

namespace App\Console\Commands;

use App\Models\DefragliveContest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Keep the $5 / 2-week contest cadence rolling: flip any active contest whose
 * window has ended to "closed" (the winner is drawn manually so payout stays in
 * human hands), then open the next two-week window continuing from the last
 * one. Does nothing until the admin has seeded the first contest - it never
 * springs a contest into existence on its own.
 */
class DefragliveRolloverContests extends Command
{
    protected $signature = 'defraglive:rollover-contests';

    protected $description = 'Close ended DefragLive contests and open the next $5 / 2-week window';

    /** Default contest length. */
    private const PERIOD_DAYS = 14;

    public function handle(): int
    {
        // 1) Close windows that have ended (leave undrawn for the admin).
        $closed = DefragliveContest::where('status', DefragliveContest::STATUS_ACTIVE)
            ->where('ends_at', '<', now())
            ->update(['status' => DefragliveContest::STATUS_CLOSED]);

        if ($closed) {
            $this->info("Closed {$closed} ended contest(s).");
        }

        // 2) Only continue an existing cadence - never seed the first contest.
        $last = DefragliveContest::orderByDesc('ends_at')->first();
        if (!$last) {
            $this->info('No contests yet - seed the first one in the admin. Nothing to roll over.');

            return self::SUCCESS;
        }

        // Is the present already covered, or is a window already lined up to
        // cover it? An active contest that has not ended yet answers both: it
        // is either running now or it starts shortly and will.
        //
        // This used to ask only whether a window covered *this exact moment*,
        // which is never true while the newest window is still in the future -
        // so every hourly run opened another one starting where the previous
        // ended, and the chain marched off into next year. Production had
        // eleven of them, one per hour, before anybody noticed. A contest
        // promises money, so a loop that mints them is not a cosmetic bug.
        $covered = DefragliveContest::where('status', DefragliveContest::STATUS_ACTIVE)
            ->where('ends_at', '>=', now())
            ->exists();

        if ($covered) {
            return self::SUCCESS;
        }

        // Where the next window starts.
        //
        // Continue straight from the last one when it has only just ended, so
        // the cadence holds and no watch time falls between two contests.
        //
        // Start from now in the two cases where continuing would be wrong:
        // a window that was closed early still has its nominal end in the
        // future, and chaining from there would leave hours belonging to no
        // contest; and a cadence dormant for months would otherwise back-fill
        // one contest per hour until it caught up, each with a prize nobody
        // promised.
        $lastEnd = $last->ends_at;
        $resumable = $lastEnd->isPast() && $lastEnd->greaterThan(now()->subDays(self::PERIOD_DAYS));

        $start = $resumable ? $lastEnd->copy() : now();
        $end = $start->copy()->addDays(self::PERIOD_DAYS);

        $contest = DefragliveContest::create([
            'title' => 'DefragLive Watch - ' . $start->format('M j') . ' to ' . $end->format('M j'),
            'starts_at' => $start,
            'ends_at' => $end,
            'prize_amount' => $last->prize_amount,
            'prize_currency' => $last->prize_currency,
            'status' => DefragliveContest::STATUS_ACTIVE,
        ]);

        $this->info("Opened contest #{$contest->id}: {$contest->title}.");

        return self::SUCCESS;
    }
}
