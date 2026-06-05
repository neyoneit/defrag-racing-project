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

        // Already covered for now? Nothing to do.
        $hasCurrent = DefragliveContest::where('status', DefragliveContest::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->exists();

        if ($hasCurrent) {
            return self::SUCCESS;
        }

        // Open the next window, continuing seamlessly from the last one.
        $start = $last->ends_at->isFuture() ? $last->ends_at->copy() : now();
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
