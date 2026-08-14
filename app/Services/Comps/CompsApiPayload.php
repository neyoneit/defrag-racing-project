<?php

namespace App\Services\Comps;

use App\Models\Comp;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\User;

/**
 * What the launcher is told about comps.
 *
 * Deliberately not built by reusing the web page's Inertia props. That payload
 * carries things the launcher has no use for - history, prize copy, ballot
 * previews - and, more to the point, the two audiences differ in what they are
 * allowed to know. Splitting one builder into two shapes is how the stricter
 * of the two eventually inherits something from the looser one.
 *
 * **No opponent's time is ever in here, in any form.** Times are hidden while a
 * round is being played because a live leaderboard hands every later entrant
 * the answer, and a payload that leaked one would make the launcher a way
 * around the rule the website enforces. Entrant counts only.
 *
 * The launcher needs exactly two things to do its job: which map is being
 * played in each physics (so it can recognise a run on it and keep that demo
 * out of the public upload path), and what the person's own entries are doing.
 */
class CompsApiPayload
{
    public function __construct(
        private CandidateSelector $selector,
        private PrizeFunding $funding,
        private UploadGuard $guard,
        private SubmissionIntake $intake,
    ) {
    }

    public function build(?User $user): array
    {
        $playing = $this->round('active');
        $voting = $this->round('voting') ?? $this->round('locked');

        return [
            'playing' => $playing ? $this->playing($playing, $user) : null,
            'voting' => $voting ? $this->voting($voting) : null,
            // Outside `playing`, because a demo can be held for a map that is
            // still being voted on - a week when there is nothing being played
            // at all, and the launcher still has to be able to say why the
            // person's demo went quiet.
            'my_notices' => $user ? $this->guard->noticesFor($user->id) : [],
            'entry_gate' => $this->entryGate($user),
        ];
    }

    /**
     * Whether this person may enter a run at all.
     *
     * A launcher token is handed to any signed-in account, linked profile or
     * not, because backing demos up and browsing servers have nothing to do
     * with comps. Entering does, and without this the launcher would find out
     * one rejected upload at a time - so it is told up front and can say the
     * one useful thing instead: link your account, here is where.
     */
    private function entryGate(?User $user): array
    {
        $reason = $this->intake->userRejectionReason($user);

        return [
            'may' => $reason === null,
            'reason' => $reason,
            'needs' => match (true) {
                $reason === null => null,
                ! $user => 'signin',
                ! $user->hasVerifiedEmail() => 'verify',
                default => 'mdd',
            },
            // Where to go and fix it. The launcher cannot build a site URL and
            // should not be inventing one.
            'settings_url' => route('settings.show'),
        ];
    }

    private function round(string $status): ?CompRound
    {
        return CompRound::where('status', $status)
            ->whereHas('comp', fn ($q) => $q->where('type', Comp::WEEKLY))
            ->with(['comp', 'maps.map'])
            ->latest('starts_at')
            ->first();
    }

    /**
     * The round being played: the two maps, the deadline, and the caller's own
     * entries.
     *
     * `ends_at` goes out in ISO 8601 with its offset. The launcher counts down
     * against it, and a bare local-looking string would be read as the user's
     * own timezone - the same confusion that once shifted a round by two hours
     * on the site itself.
     */
    private function playing(CompRound $round, ?User $user): array
    {
        return [
            'round_id' => $round->id,
            'comp_number' => (int) $round->comp->number,
            'category' => $round->category,
            'weapon' => $round->weapon,
            'ends_at' => $round->ends_at?->toIso8601String(),
            'prize_eur' => (float) ($round->prize_eur ?? $this->funding->perPhysicsFor((int) $round->comp->number)),
            'maps' => $round->maps->mapWithKeys(fn ($m) => [$m->physics => $m->map?->name])->all(),
            // A count, never a list, and never a time. It answers "is anyone
            // else in this" without answering "what do I have to beat".
            'entrants' => $this->entrantCounts($round),
            'my_entries' => $user ? $this->myEntries($round, $user) : [],
        ];
    }

    private function entrantCounts(CompRound $round): array
    {
        $rows = CompSubmission::where('comp_round_id', $round->id)
            ->where('status', 'valid')
            ->whereNotNull('physics')
            ->selectRaw('physics, COUNT(DISTINCT user_id) AS n')
            ->groupBy('physics')
            ->pluck('n', 'physics');

        return collect(BallotResolver::PHYSICS)
            ->mapWithKeys(fn ($p) => [$p => (int) ($rows[$p] ?? 0)])
            ->all();
    }

    /**
     * The caller's own entries, including their own times - those are theirs to
     * see. `invalid_reason` is already a finished, translated sentence, so the
     * launcher prints it without having to know anything about comps rules.
     */
    private function myEntries(CompRound $round, User $user): array
    {
        return CompSubmission::where('comp_round_id', $round->id)
            ->where('user_id', $user->id)
            ->with(['demo' => fn ($q) => $q->withUnreleasedComps()])
            ->orderBy('id')
            ->get()
            ->map(fn (CompSubmission $s) => [
                'id' => $s->id,
                'status' => $s->status,
                'physics' => $s->physics,
                'time' => $s->time ?: null,
                'invalid_reason' => $s->invalid_reason,
                'is_highlight' => (bool) $s->is_highlight,
                'auto_entered' => (bool) $s->auto_entered,
                'filename' => $s->demo?->original_filename,
                'file_hash' => $s->demo?->file_hash,
            ])
            ->all();
    }

    /**
     * The open ballot, as names and nothing else.
     *
     * No preview videos and no vote counts: the launcher cannot play the
     * previews, and voting happens on the site, so a ballot here is a heads-up
     * about what next week might be - not a voting booth.
     */
    private function voting(CompRound $round): array
    {
        return [
            'round_id' => $round->id,
            'comp_number' => (int) $round->comp->number,
            'category' => $round->category,
            'closes_at' => $round->voting_closes_at?->toIso8601String(),
            'is_open' => $round->isVoting() && $round->voting_closes_at?->isFuture(),
            'candidates' => $round->candidates()->with('map:id,name')->get()
                ->map(fn ($c) => $c->map?->name)
                ->filter()
                ->values()
                ->all(),
            'next_category' => $this->selector->categoryForWeekly((int) $round->comp->number + 1),
        ];
    }
}
