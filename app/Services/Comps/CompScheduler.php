<?php

namespace App\Services\Comps;

use App\Models\Comp;
use App\Models\CompCandidate;
use App\Models\CompRound;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Drives the weekly forward. Everything here is idempotent and driven off the
 * clock, so it can be run every minute and does nothing on the minutes where
 * nothing is due.
 *
 * The cycle, taking Sunday 20:00 as the hinge:
 *
 *   Sun 20:00  week N starts. Week N+1 is created at the same moment with its
 *              ballot open, so there is never a gap where nobody can vote.
 *   Sat 20:00  the ballot for week N+1 closes and its map is decided.
 *   Sun 20:00  week N ends - results frozen, wildcards awarded, demos become
 *              downloadable - and week N+1 starts. And so on.
 *
 * Which means a round is created a full week before it is played and spends
 * that week collecting votes.
 */
class CompScheduler
{
    public function __construct(
        private CompSettings $settings,
        private CandidateSelector $selector,
        private BallotResolver $resolver,
        private ResultsCalculator $results,
        private WildcardService $wildcards,
        private CompPreviewService $previews,
    ) {
    }

    /**
     * One pass. Returns what it did, for the command's output and the log.
     *
     * @return array<int, string>
     */
    public function tick(): array
    {
        if (! $this->settings->weeklyEnabled()) {
            return [];
        }

        $done = [];

        $done = array_merge($done, $this->closeDueBallots());
        $done = array_merge($done, $this->finishDueRounds());
        $done = array_merge($done, $this->startDueRounds());
        $done = array_merge($done, $this->ensureUpcomingWeekly());

        foreach ($done as $line) {
            Log::info('[comps] ' . $line);
        }

        return $done;
    }

    /**
     * Ballots whose closing time has passed: decide the map, lock the round.
     */
    private function closeDueBallots(): array
    {
        $done = [];

        $rounds = CompRound::where('status', 'voting')
            ->where('voting_closes_at', '<=', now())
            ->get();

        foreach ($rounds as $round) {
            $this->resolver->resolve($round);
            $round->update(['status' => 'locked']);

            $picked = $round->maps->map(fn ($m) => $m->physics . '=' . $m->map_id . ' (' . $m->decided_by . ')')->implode(', ');
            $done[] = "round {$round->id}: ballot closed, {$picked}";
        }

        return $done;
    }

    /**
     * Rounds past their end: freeze standings, then close the comp if that was
     * its last round, which is what makes wildcards fall due.
     */
    private function finishDueRounds(): array
    {
        $done = [];

        $rounds = CompRound::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($rounds as $round) {
            $this->results->freeze($round);
            $round->update(['status' => 'finished']);

            // Release the demos. The timestamp on each would have expired on
            // its own, but only if the round ended when it was written to say
            // it would - an admin moving the end time would otherwise leave
            // them hidden for a week or published early.
            $released = DB::table('uploaded_demos')
                ->whereIn('id', DB::table('comp_submissions')
                    ->where('comp_round_id', $round->id)
                    ->select('uploaded_demo_id'))
                ->update(['comps_hidden_until' => null]);

            $done[] = "round {$round->id}: finished, standings frozen, {$released} demo(s) released";

            $comp = $round->comp;

            $unfinished = $comp->rounds()->where('status', '!=', 'finished')->exists();

            if (! $unfinished) {
                $comp->update(['status' => 'finished', 'ends_at' => $round->ends_at]);
                $granted = $this->wildcards->awardFor($comp);
                $done[] = "comp {$comp->id} ({$comp->title}): finished, {$granted} wildcard(s) awarded";
            }
        }

        return $done;
    }

    /** Locked rounds whose start has come. */
    private function startDueRounds(): array
    {
        $done = [];

        $rounds = CompRound::where('status', 'locked')
            ->where('starts_at', '<=', now())
            ->get();

        foreach ($rounds as $round) {
            $round->update(['status' => 'active']);
            $round->comp()->update(['status' => 'active']);
            $done[] = "round {$round->id}: now playing";
        }

        return $done;
    }

    /**
     * There must always be exactly one weekly taking votes. Creating it here
     * rather than on a fixed cron means a fresh install bootstraps itself: the
     * first tick makes week one, and every tick after finds one already open
     * and does nothing.
     */
    private function ensureUpcomingWeekly(): array
    {
        $open = Comp::weekly()
            ->whereHas('rounds', fn ($q) => $q->whereIn('status', ['voting', 'locked']))
            ->exists();

        if ($open) {
            return [];
        }

        $comp = $this->createNextWeekly();

        if (! $comp) {
            return ['no weekly created: the category pool is empty'];
        }

        // Ask for the preview renders now rather than when somebody opens the
        // page. There is a week before the ballot closes and another day
        // before the map is played, which is the whole reason that day exists.
        $queued = $this->previews->queueMissing($comp->rounds->first());

        return ["comp {$comp->id} ({$comp->title}): created, ballot open, {$queued} preview render(s) queued"];
    }

    /**
     * Build the next weekly - its round, its dates and its ballot.
     */
    public function createNextWeekly(): ?Comp
    {
        $number = (int) Comp::weekly()->max('number') + 1;

        $starts = $this->nextStartAfter($this->latestWeeklyEnd() ?? now());
        $ends = $starts->copy()->addWeek();
        $votingCloses = $starts->copy()->subHours($this->settings->votingLeadHours());

        $category = $this->selector->categoryForWeekly($number);
        $weapon = $category === MapClassifier::WEAPON ? $this->selector->drawWeapon() : null;

        $draw = $this->selector->draw($category, $weapon, $this->settings->poolSize());

        if (empty($draw)) {
            Log::warning("[comps] weekly #{$number} not created: no eligible maps for {$category}" . ($weapon ? " ({$weapon})" : ''));

            return null;
        }

        return DB::transaction(function () use ($number, $starts, $ends, $votingCloses, $category, $weapon, $draw) {
            $comp = Comp::create([
                'type' => Comp::WEEKLY,
                'number' => $number,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'status' => 'upcoming',
            ]);

            $round = CompRound::create([
                'comp_id' => $comp->id,
                'index' => 1,
                'category' => $category,
                'weapon' => $weapon,
                // Voting opens immediately: the previous week is being played
                // and this is the ballot that runs alongside it.
                'voting_opens_at' => now(),
                'voting_closes_at' => $votingCloses,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'status' => 'voting',
            ]);

            foreach ($draw as $map) {
                CompCandidate::create([
                    'comp_round_id' => $round->id,
                    'map_id' => $map['id'],
                    'blocked_physics' => $map['blocked_physics'] ?? null,
                ]);
            }

            return $comp;
        });
    }

    /** The end of the last weekly we know about, so the next one abuts it. */
    private function latestWeeklyEnd(): ?Carbon
    {
        $at = Comp::weekly()->max('ends_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * The first configured start moment strictly after the given time. On a
     * fresh install that is the coming Sunday; afterwards it is simply the
     * moment the previous week ends.
     *
     * Returned in the application timezone, and that matters. Eloquent writes
     * a Carbon by formatting it, without converting the zone first, so a
     * Prague-flavoured 20:00 handed straight to the model is stored as the
     * string 20:00 and read back as 20:00 UTC - which is 22:00 in Prague, an
     * hour and a season adrift from what the admin asked for.
     */
    public function nextStartAfter(Carbon $after): Carbon
    {
        $tz = $this->settings->timezone();
        [$hour, $minute] = array_pad(explode(':', $this->settings->startTime()), 2, '0');

        $candidate = $after->copy()
            ->setTimezone($tz)
            ->startOfDay()
            ->setTime((int) $hour, (int) $minute);

        // Walk forward to the configured weekday, then a week further if that
        // landed on or before the moment we were given.
        while ($candidate->dayOfWeek !== $this->settings->startDayOfWeek()) {
            $candidate->addDay();
        }

        if ($candidate->lessThanOrEqualTo($after)) {
            $candidate->addWeek();
        }

        return $candidate->setTimezone(config('app.timezone'));
    }
}
