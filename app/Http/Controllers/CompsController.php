<?php

namespace App\Http\Controllers;

use App\Models\Comp;
use App\Models\CompCandidate;
use App\Models\CompResult;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\CompVote;
use App\Models\CompWildcard;
use App\Services\Comps\BallotResolver;
use App\Services\Comps\CompPreviewService;
use App\Services\Comps\CompSettings;
use App\Services\Comps\ResultsCalculator;
use App\Services\Comps\WildcardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Comps: the competition that runs itself.
 *
 * Every week the site draws five maps in a rotating category, everyone votes
 * on them in each physics, and the winners are played for a week. Nobody
 * organises it and nobody can forget to.
 *
 * Two things here are load-bearing and worth stating plainly.
 *
 * Times are hidden while a round is being played. A live leaderboard of route
 * times would hand every later entrant the answer, so the standings show who
 * has entered and nothing else until the round closes.
 *
 * Demos are never downloadable during a round, and are only ever entries
 * because somebody uploaded them here. Serverdemos are collected from bundle
 * servers automatically and comps does not read them at all.
 */
class CompsController extends Controller
{
    public function __construct(
        private CompPreviewService $previews,
        private WildcardService $wildcards,
        private ResultsCalculator $results,
    ) {
    }

    /**
     * The hub: what is being played, what is being voted on, and who has won
     * before.
     */
    public function index(Request $request)
    {
        $playing = $this->roundWithStatus('active');
        $voting = $this->roundWithStatus('voting') ?? $this->roundWithStatus('locked');

        return Inertia::render('Comps/Index', [
            'playing' => $playing ? $this->playingPayload($playing, $request) : null,
            'voting' => $voting ? $this->votingPayload($voting, $request) : null,
            'history' => $this->history(),
            'me' => $request->user() ? $this->myStanding($request->user()->id) : null,
            'pointsTable' => ResultsCalculator::POINTS,
            'pointsForFinishing' => ResultsCalculator::POINTS_FOR_FINISHING,
            'winsPerWildcard' => CompWildcard::WEEKLY_WINS_REQUIRED,
            'prize' => $this->prize(),
            'betaNotice' => app(CompSettings::class)->betaNotice(),
            // Where "tell the admin" goes. Built here rather than in the page
            // so the id is not a literal sitting in a Vue file.
            'adminUrl' => route('profile.index', app(CompSettings::class)->contactUserId()),
        ]);
    }

    /**
     * What a weekly pays, and who is paying for it.
     *
     * The first weeks are paid out by neyo, and after those the pool may be
     * paid out by him again or by the community. Both numbers are settings,
     * because a prize is a promise to players and the person making it should
     * be able to change it without waiting for a deploy - and because a
     * promise that has run out should stop being displayed on its own rather
     * than sit there until somebody remembers to take it down.
     */
    private function prize(): array
    {
        $settings = app(CompSettings::class);

        $eur = $settings->prizeEur();
        $fundedWeeks = $settings->prizeFundedWeeks();

        // The highest week that exists, which is the one being played or voted
        // on rather than the last one finished - so week 5 of 5 still counts
        // as funded while it is being run, not only until it starts.
        $current = (int) Comp::weekly()->max('number');

        return [
            'eur' => $eur,
            // Both physics are paid, so the week costs twice the setting. The
            // total is what a reader wants first and the per-physics figure is
            // what they need to not misread it, so the page shows both.
            'total' => $eur * count(BallotResolver::PHYSICS),
            'funded_weeks' => $fundedWeeks,
            // The whole commitment, which reads very differently from the
            // weekly figure and is the number people actually repeat to each
            // other. Derived, so changing either setting keeps it honest.
            'funded_total' => $eur * count(BallotResolver::PHYSICS) * $fundedWeeks,
            'self_funded' => $eur > 0 && $current <= $fundedWeeks,
        ];
    }

    /** A finished comp, opened from the history list. */
    public function show(Comp $comp)
    {
        abort_unless($comp->status === 'finished', 404);

        $comp->load(['rounds.maps.map', 'rounds.results.user']);

        return Inertia::render('Comps/Show', [
            'comp' => [
                'id' => $comp->id,
                'title' => $comp->title,
                'type' => $comp->type,
                'starts_at' => $comp->starts_at,
                'ends_at' => $comp->ends_at,
                'rounds' => $comp->rounds->map(fn (CompRound $r) => [
                    'id' => $r->id,
                    'index' => $r->index,
                    'category' => $r->category,
                    'weapon' => $r->weapon,
                    'starts_at' => $r->starts_at,
                    'ends_at' => $r->ends_at,
                    'maps' => $r->maps->mapWithKeys(fn ($m) => [$m->physics => [
                        'name' => $m->map?->name,
                        'decided_by' => $m->decided_by,
                    ]]),
                    'results' => $this->resultsPayload($r),
                ]),
            ],
        ]);
    }

    /**
     * Cast or move a vote. One per person per physics, changeable right up to
     * the deadline - a vote is a preference, not a commitment.
     */
    public function vote(Request $request, CompRound $round)
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'physics' => ['required', 'in:cpm,vq3'],
        ]);

        $this->assertMayVote($request);

        abort_unless($round->isVoting() && $round->voting_closes_at->isFuture(), 403, __('Voting for this round is closed.'));

        $candidate = CompCandidate::where('comp_round_id', $round->id)
            ->findOrFail($data['candidate_id']);

        abort_unless($candidate->votableIn($data['physics']), 403, __('That map cannot be finished in this physics.'));

        DB::transaction(function () use ($request, $round, $candidate, $data) {
            $existing = CompVote::where('comp_round_id', $round->id)
                ->where('user_id', $request->user()->id)
                ->where('physics', $data['physics'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->comp_candidate_id === $candidate->id) {
                    return;
                }

                CompCandidate::where('id', $existing->comp_candidate_id)
                    ->decrement($this->voteColumn($data['physics']));

                $existing->delete();
            }

            CompVote::create([
                'comp_round_id' => $round->id,
                'comp_candidate_id' => $candidate->id,
                'user_id' => $request->user()->id,
                'physics' => $data['physics'],
            ]);

            $candidate->increment($this->voteColumn($data['physics']));
        });

        return back();
    }

    /**
     * Spend a wildcard. First one in decides the round; anybody else holding
     * one keeps theirs for another week and is told why.
     */
    public function useWildcard(Request $request, CompRound $round)
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'physics' => ['required', 'in:cpm,vq3'],
        ]);

        $this->assertMayVote($request);

        $wildcard = $this->wildcards->heldBy($request->user()->id, $data['physics']);

        abort_unless($wildcard, 403, __('You do not hold a wildcard for this physics.'));

        $candidate = CompCandidate::where('comp_round_id', $round->id)
            ->findOrFail($data['candidate_id']);

        try {
            $this->wildcards->spend($wildcard, $round, $candidate);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wildcard' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Where the reader stands in comps: what they hold, what they have won,
     * and how close the next wildcard is.
     *
     * The wildcard was invisible until this existed - the only sign you had one
     * was a button appearing on the ballot, which is no use in the six days a
     * week when there is nothing to spend it on and no help at all in telling
     * you that four more weekly wins earns another.
     */
    private function myStanding(int $userId): array
    {
        $wildcards = CompWildcard::where('user_id', $userId)->get();

        $out = [
            'held' => [],
            'spent' => [],
            'wins' => [],
            'wins_to_next' => [],
            'rounds_entered' => 0,
            'average_rank' => null,
            'best_rank' => null,
        ];

        $finishedWeeklyRounds = CompRound::query()
            ->whereHas('comp', fn ($q) => $q->where('type', Comp::WEEKLY)->where('status', 'finished'))
            ->pluck('id');

        foreach (BallotResolver::PHYSICS as $physics) {
            $ofPhysics = $wildcards->where('physics', $physics);

            $out['held'][$physics] = $ofPhysics->whereNull('used_at')->count();
            $out['spent'][$physics] = $ofPhysics->whereNotNull('used_at')->count();

            $wins = CompResult::where('user_id', $userId)
                ->where('physics', $physics)
                ->winners()
                ->whereIn('comp_round_id', $finishedWeeklyRounds)
                ->count();

            $out['wins'][$physics] = $wins;

            // How many more weekly wins earn the next one. Counted off the
            // running total rather than from the last award, so it stays right
            // however the wins are spread across a year.
            $needed = CompWildcard::WEEKLY_WINS_REQUIRED;
            $out['wins_to_next'][$physics] = $needed - ($wins % $needed);
        }

        $ranks = CompResult::where('user_id', $userId)->pluck('rank');

        if ($ranks->isNotEmpty()) {
            $out['rounds_entered'] = $ranks->count();
            $out['average_rank'] = round($ranks->avg(), 1);
            $out['best_rank'] = (int) $ranks->min();
        }

        return $out;
    }

    /** Which round is at a given stage. Weekly only ever has one at a time. */
    private function roundWithStatus(string $status): ?CompRound
    {
        return CompRound::where('status', $status)
            ->whereHas('comp', fn ($q) => $q->where('type', Comp::WEEKLY))
            ->with(['comp', 'candidates.map', 'maps.map'])
            ->orderBy('starts_at')
            ->first();
    }

    private function votingPayload(CompRound $round, Request $request): array
    {
        $previews = $this->previews->forRound($round);
        $user = $request->user();

        $myVotes = $user
            ? CompVote::where('comp_round_id', $round->id)
                ->where('user_id', $user->id)
                ->pluck('comp_candidate_id', 'physics')
            : collect();

        $wildcardsHeld = [];
        foreach (BallotResolver::PHYSICS as $physics) {
            $wildcardsHeld[$physics] = $user
                ? (bool) $this->wildcards->heldBy($user->id, $physics)
                : false;
        }

        return [
            'round_id' => $round->id,
            'comp_title' => $round->comp->title,
            'category' => $round->category,
            'weapon' => $round->weapon,
            'closes_at' => $round->voting_closes_at,
            'starts_at' => $round->starts_at,
            'is_open' => $round->isVoting() && $round->voting_closes_at->isFuture(),
            'decided' => $round->maps->mapWithKeys(fn ($m) => [$m->physics => [
                'map' => $m->map?->name,
                'decided_by' => $m->decided_by,
            ]]),
            'candidates' => $round->candidates->map(fn (CompCandidate $c) => [
                'id' => $c->id,
                'map_id' => $c->map_id,
                'map' => $c->map?->name,
                'thumbnail' => $c->map?->thumbnail,
                'author' => $c->map?->author,
                'blocked_physics' => $c->blocked_physics,
                'votes' => ['cpm' => $c->votes_cpm, 'vq3' => $c->votes_vq3],
                'preview' => $previews[$c->map_id] ?? null,
            ])->values(),
            'my_votes' => $myVotes,
            'wildcards_held' => $wildcardsHeld,
            'may_vote' => $this->mayVote($request),
        ];
    }

    private function playingPayload(CompRound $round, Request $request): array
    {
        $user = $request->user();

        return [
            'round_id' => $round->id,
            'comp_title' => $round->comp->title,
            'category' => $round->category,
            'weapon' => $round->weapon,
            'ends_at' => $round->ends_at,
            'maps' => $round->maps->mapWithKeys(fn ($m) => [$m->physics => [
                'name' => $m->map?->name,
                'thumbnail' => $m->map?->thumbnail,
                'author' => $m->map?->author,
                'decided_by' => $m->decided_by,
            ]]),
            // Times stay hidden until the round closes, so this is who has
            // entered rather than who is winning.
            'entrants' => $this->entrants($round),
            'removed_entrants' => $this->removedEntrants($round),
            'my_entries' => $user ? $this->myEntries($round, $user->id) : [],
        ];
    }

    /**
     * Who has entered, per physics, without saying how fast. Enough for people
     * to see the round is alive; not enough to tell anybody what to beat.
     */
    private function entrants(CompRound $round): array
    {
        $rows = CompSubmission::query()
            ->counting()
            ->where('comp_round_id', $round->id)
            ->with('user:id,name,country,profile_photo_path,name_effect,color')
            ->get()
            ->groupBy('physics');

        $out = [];

        foreach (BallotResolver::PHYSICS as $physics) {
            $out[$physics] = ($rows->get($physics) ?? collect())
                ->unique('user_id')
                ->map(fn (CompSubmission $s) => [
                    'id' => $s->user?->id,
                    'name' => $s->user?->name,
                    'country' => $s->user?->country,
                    'photo' => $s->user?->profile_photo_path,
                    'name_effect' => $s->user?->name_effect,
                    'color' => $s->user?->color,
                ])
                ->values();
        }

        return $out;
    }

    /**
     * People whose run an admin took out, shown alongside the entrants rather
     * than deleted from the list.
     *
     * Silently dropping them would make the round page read as though they
     * never turned up, which is both unfair to them and confusing to everyone
     * who watched them enter. Only admin removals appear here: a run the
     * validator refused - wrong map, unreadable file - is somebody picking the
     * wrong demo, and publishing that would be publishing their slip.
     *
     * Anybody who still has a counting run in that physics is left out: they
     * had one entry pulled and another accepted, and they are simply an
     * entrant.
     */
    private function removedEntrants(CompRound $round): array
    {
        $rows = CompSubmission::query()
            ->removedByAdmin()
            ->where('comp_round_id', $round->id)
            ->whereNotNull('physics')
            ->with('user:id,name,country,profile_photo_path,name_effect,color')
            ->get()
            ->groupBy('physics');

        $stillIn = CompSubmission::query()
            ->counting()
            ->where('comp_round_id', $round->id)
            ->get()
            ->groupBy('physics')
            ->map(fn ($g) => $g->pluck('user_id')->all());

        $out = [];

        foreach (BallotResolver::PHYSICS as $physics) {
            $keep = $stillIn->get($physics, []);

            $out[$physics] = ($rows->get($physics) ?? collect())
                ->reject(fn (CompSubmission $s) => in_array($s->user_id, $keep, true))
                ->unique('user_id')
                ->map(fn (CompSubmission $s) => [
                    'id' => $s->user?->id,
                    'name' => $s->user?->name,
                    'country' => $s->user?->country,
                    'photo' => $s->user?->profile_photo_path,
                    'name_effect' => $s->user?->name_effect,
                    'color' => $s->user?->color,
                    'reason' => $s->invalid_reason,
                ])
                ->values();
        }

        return $out;
    }

    /** Your own entries, times and all. Yours are never a secret from you. */
    private function myEntries(CompRound $round, int $userId): array
    {
        return CompSubmission::where('comp_round_id', $round->id)
            ->where('user_id', $userId)
            ->with(['demo' => fn ($q) => $q->withUnreleasedComps()])
            ->orderByRaw('physics IS NULL')
            ->orderBy('physics')
            ->orderBy('time')
            ->get()
            ->map(fn (CompSubmission $s) => [
                'id' => $s->id,
                'physics' => $s->physics,
                'time' => $s->time,
                // Online is the gametype the run was made in, not whether we
                // paired it with a record. The pairing is the separate, and
                // purely decorative, fact below it.
                'is_online' => (bool) $s->is_online,
                'gametype' => $s->demo?->gametype,
                'matched_record' => $s->matched_record_id !== null,
                'is_highlight' => $s->is_highlight,
                'status' => $s->status,
                'reason' => $s->invalid_reason,
                'filename' => $s->demo?->original_filename,
            ])
            ->all();
    }

    private function resultsPayload(CompRound $round): array
    {
        $out = [];

        foreach (BallotResolver::PHYSICS as $physics) {
            $out[$physics] = CompResult::where('comp_round_id', $round->id)
                ->where('physics', $physics)
                ->with('user:id,name,country,profile_photo_path,name_effect,color')
                ->orderBy('rank')
                ->orderBy('time')
                ->get()
                ->map(fn (CompResult $r) => [
                    'rank' => $r->rank,
                    'time' => $r->time,
                    'points' => (float) $r->points,
                    'user' => [
                        'id' => $r->user?->id,
                        'name' => $r->user?->name,
                        'country' => $r->user?->country,
                        'photo' => $r->user?->profile_photo_path,
                        'name_effect' => $r->user?->name_effect,
                        'color' => $r->user?->color,
                    ],
                ])
                ->all();
        }

        return $out;
    }

    /** Finished comps, most recent first, with their winners. */
    private function history(int $limit = 12): array
    {
        $comps = Comp::where('status', 'finished')
            ->orderByDesc('ends_at')
            ->limit($limit)
            ->with('rounds.maps.map')
            ->get();

        return $comps->map(function (Comp $comp) {
            $winners = [];

            foreach (BallotResolver::PHYSICS as $physics) {
                $winners[$physics] = CompResult::whereIn('comp_round_id', $comp->rounds->pluck('id'))
                    ->where('physics', $physics)
                    ->winners()
                    ->with('user:id,name,country,profile_photo_path,name_effect,color')
                    ->get()
                    ->map(fn (CompResult $r) => [
                        'id' => $r->user?->id,
                        'name' => $r->user?->name,
                        'country' => $r->user?->country,
                        'photo' => $r->user?->profile_photo_path,
                        'name_effect' => $r->user?->name_effect,
                        'color' => $r->user?->color,
                        'time' => $r->time,
                    ])
                    ->values();
            }

            return [
                'id' => $comp->id,
                'title' => $comp->title,
                'type' => $comp->type,
                'ends_at' => $comp->ends_at,
                'maps' => $comp->rounds->flatMap(fn (CompRound $r) => $r->maps->pluck('map.name'))->unique()->values(),
                'winners' => $winners,
            ];
        })->all();
    }

    /**
     * Voting needs an account with a linked MDD profile. The prize for winning
     * is a wildcard, so a bare sign-up form would be an invitation to vote with
     * as many accounts as you can be bothered to make.
     */
    private function mayVote(Request $request): bool
    {
        return $request->user() !== null && $request->user()->mdd_id !== null;
    }

    private function assertMayVote(Request $request): void
    {
        abort_unless($request->user(), 403);
        abort_unless(
            $request->user()->mdd_id,
            403,
            __('Link your MDD profile to vote in comps.')
        );
    }

    private function voteColumn(string $physics): string
    {
        return $physics === 'cpm' ? 'votes_cpm' : 'votes_vq3';
    }
}
