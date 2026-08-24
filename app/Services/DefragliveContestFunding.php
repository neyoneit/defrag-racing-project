<?php

namespace App\Services;

use App\Models\DefragliveContest;
use App\Models\SiteDonation;
use App\Models\User;

/**
 * Where a DefragLive contest prize comes from, written down.
 *
 * The prizes were being paid privately and appeared nowhere, so the donations
 * page counted every euro it knew about towards the hosting goal - including
 * euro that were already promised to a contest winner. The bar read higher
 * than the bills were actually covered by, by exactly the prize money.
 *
 * So each contest records the money that pays for it, as an ordinary donation
 * earmarked for the pool. It adds nothing to the goal, which is the point: the
 * money was never for hosting. What it does is make the promise visible, the
 * same way comps does.
 */
class DefragliveContestFunding
{
    /** Whoever is putting up the prize money. */
    public function funder(): ?User
    {
        return User::where('email', config('app.admin_email'))->first();
    }

    /**
     * What a contest costs the person funding it.
     *
     * Its own prize, less anything carried over from an earlier winner who
     * gave theirs forward. That part was funded once already, when the contest
     * it came from was created; counting it again here would say the same five
     * euro were put in twice.
     */
    public function baseAmount(DefragliveContest $contest): float
    {
        return max(0, round((float) $contest->prize_amount - (float) $contest->carried_over_amount, 2));
    }

    /**
     * Write, correct or remove the funding row for one contest.
     *
     * A draft is not funded. It is a contest somebody is still writing, it may
     * never run, and a public donation towards a prize that never existed is
     * worse than no row at all - so a draft has its row taken away again if it
     * had one.
     */
    public function record(DefragliveContest $contest): ?SiteDonation
    {
        $existing = SiteDonation::where('defraglive_contest_id', $contest->id)->first();

        $amount = $this->baseAmount($contest);

        if ($contest->status === DefragliveContest::STATUS_DRAFT || $amount <= 0) {
            $existing?->delete();

            return null;
        }

        $funder = $this->funder();

        if (! $funder) {
            return null;
        }

        $fields = [
            'user_id' => $funder->id,
            // Donor stats are aggregated by email, so this is what makes the
            // row belong to anybody at all.
            'donor_email' => $funder->email,
            'donor_name' => trim((string) preg_replace('/\^[0-9A-Za-z]/', '', (string) $funder->name)) ?: 'defrag.racing',
            'amount' => $amount,
            // The contest's own currency on the donation, EUR on the earmark.
            // The pool has no conversion anywhere, exactly as the comps pool
            // has none, and the contest resource already warns before a
            // non-EUR prize is settled into one.
            'currency' => $contest->prize_currency ?: 'EUR',
            // The contest's own date, so the money lands in the year whose
            // goal it is being kept out of.
            'donation_date' => ($contest->starts_at ?? now())->toDateString(),
            'note' => 'Prize money for the DefragLive contest "' . $contest->title . '" - ' . url('/defraglive/contest'),
            'status' => 'approved',
            'defraglive_amount' => $amount,
            'defraglive_note' => 'Funds the DefragLive contest prize.',
        ];

        if ($existing) {
            $existing->update($fields);

            return $existing;
        }

        return SiteDonation::create($fields + ['defraglive_contest_id' => $contest->id]);
    }

    /** Contests that ran before any of this existed. */
    public function backfill(): int
    {
        $written = 0;

        DefragliveContest::orderBy('starts_at')->each(function (DefragliveContest $contest) use (&$written) {
            $before = SiteDonation::where('defraglive_contest_id', $contest->id)->exists();

            if (! $before && $this->record($contest)) {
                $written++;
            }
        });

        return $written;
    }

    /** Everything put into the pool, ever, in euro. */
    public function pool(): float
    {
        return round((float) SiteDonation::fundsDefraglive()->sum('defraglive_amount'), 2);
    }
}
