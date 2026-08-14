<?php

namespace App\Services\Comps;

use App\Models\CompRound;
use App\Models\SiteDonation;
use Illuminate\Support\Collection;

/**
 * Who is paying for which weeks, and what that makes a week worth.
 *
 * Before this existed there was one number in settings and it applied to every
 * week forever. That is fine while one person quietly funds the whole thing,
 * and useless the moment somebody hands over a lump sum: 150 EUR is not a
 * weekly rate, it is a rate and a stretch of weeks, and the two have to be
 * written down together or the money has nowhere to live.
 *
 * So a donation says how much of itself goes to comps, over how many weeklies,
 * starting from which one. This turns those rows into the two answers the rest
 * of the site needs: what does weekly N pay, and who paid for it.
 *
 * Donations covering the same week **stack**. Two people funding the same
 * stretch should raise it, not take turns.
 */
class PrizeFunding
{
    private ?Collection $loaded = null;

    public function __construct(private CompSettings $settings)
    {
    }

    /**
     * What one physics wins in weekly number `$compNumber`, in euro.
     *
     * Falls back to the flat setting when nothing funds that week, because an
     * unfunded week is not necessarily a week that pays nothing - it is much
     * more often a week nobody has got round to recording yet, and dropping
     * the prize to zero would announce that to everyone.
     */
    public function perPhysicsFor(int $compNumber): float
    {
        $funded = $this->covering($compNumber)
            ->sum(fn (SiteDonation $d) => $d->compsPerPhysics());

        return $funded > 0 ? round($funded, 2) : (float) $this->settings->prizeEur();
    }

    /**
     * What one physics wins in this round, in euro.
     *
     * A round stamps the amount when it is created and keeps it, so that a
     * week pays what its players were told it would. That is right for a week
     * being played and wrong for one that has not started: money donated
     * today has to be able to raise next week, and a round created before the
     * donation existed would otherwise sit there advertising the old flat rate
     * while the page above it says the pool is fifty euro over ten weeks.
     *
     * So a round that has not started yet is quoted from the pool as it stands
     * right now, and one that has started keeps its stamp. The stamp is
     * refreshed at the moment a round starts - see CompScheduler.
     */
    public function forRound(CompRound $round): float
    {
        $number = (int) ($round->comp?->number ?? 0);

        if ($round->starts_at?->isFuture()) {
            return $this->perPhysicsFor($number);
        }

        return (float) ($round->prize_eur ?? $this->perPhysicsFor($number));
    }

    /** Every donation currently paying for the given weekly. */
    public function covering(int $compNumber): Collection
    {
        return $this->all()->filter(fn (SiteDonation $d) => $d->compsCovers($compNumber))->values();
    }

    /**
     * Everyone who has put money into the pool, newest first, as flat rows for
     * the page.
     *
     * `weeks` and the span are shown rather than only the amount, because
     * "50 EUR" and "50 EUR over ten weeks" are different promises and the
     * second one is the one that was actually made.
     */
    public function donors(): array
    {
        return $this->all()
            ->sortByDesc(fn (SiteDonation $d) => [$d->comps_start_comp, $d->id])
            ->values()
            ->map(fn (SiteDonation $d) => [
                'id' => $d->id,
                'name' => $d->donor_name ?: ($d->user?->name ?: __('Anonymous')),
                'user_id' => $d->user_id,
                'amount' => (float) $d->comps_amount,
                'weeks' => (int) $d->comps_weeks,
                'per_physics' => $d->compsPerPhysics(),
                'from_comp' => (int) $d->comps_start_comp,
                'to_comp' => $d->compsEndComp(),
                'note' => $d->comps_note,
            ])
            ->all();
    }

    /**
     * How many weeklies the pool actually pays for.
     *
     * The union, not the sum of everybody's spans: two people funding weeks
     * 3-12 have funded ten weeks between them, not twenty, and the headline
     * figure on the page divides the whole pool by this number.
     */
    public function fundedWeekCount(): int
    {
        $weeks = [];

        foreach ($this->all() as $d) {
            $end = $d->compsEndComp();

            for ($n = (int) $d->comps_start_comp; $n <= $end; $n++) {
                $weeks[$n] = true;
            }
        }

        return count($weeks);
    }

    /** Total put into the pool by everyone, ever. */
    public function totalDonated(): float
    {
        return round((float) $this->all()->sum(fn (SiteDonation $d) => (float) $d->comps_amount), 2);
    }

    /**
     * The last weekly any current funding reaches, or null when nothing is
     * funded. Lets the page say how far ahead the pool is paid up.
     */
    public function fundedThroughComp(): ?int
    {
        $ends = $this->all()->map(fn (SiteDonation $d) => $d->compsEndComp())->filter();

        return $ends->isEmpty() ? null : (int) $ends->max();
    }

    /**
     * Loaded once per instance. The page asks what several different weeks pay
     * and then who paid for them, and every one of those would be its own
     * query - on a list that will not realistically pass a few dozen rows in
     * years.
     *
     * An instance property, deliberately not a `static` inside the method:
     * under Octane the worker outlives the request, so a static would serve
     * yesterday's donations until the next reload. It does mean anything that
     * resolves this service twice in one request pays twice - inject it, do
     * not call app() in a loop.
     */
    private function all(): Collection
    {
        return $this->loaded ??= SiteDonation::fundsComps()->with('user:id,name')->get();
    }
}
