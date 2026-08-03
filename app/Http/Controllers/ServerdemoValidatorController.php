<?php

namespace App\Http\Controllers;

use App\Models\ServerdemoValidatorApplication;
use App\Models\ServerdemoValidatorVote;
use App\Models\ServerdemoValidatorVoteRound;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Applications for the serverdemo validator position, and the vote that
 * decides them.
 *
 * The page is public on purpose - it explains how reported runs get reviewed,
 * and that is worth reading whether or not you intend to apply. Applying and
 * voting need an account; voting additionally needs an application of your
 * own, because the people doing this work pick who they work with.
 */
class ServerdemoValidatorController extends Controller
{
    /**
     * Who may vote: anyone who put their name forward and was not turned
     * down - including the people already doing the job. They know it best
     * and have the most to lose by picking badly.
     */
    private const VOTER_STATUSES = ['pending', 'shortlisted', 'approved'];

    /** Who is on the ballot: only people still seeking the position. */
    private const CANDIDATE_STATUSES = ['pending', 'shortlisted'];

    public function index(Request $request)
    {
        $user = $request->user();
        $round = ServerdemoValidatorVoteRound::current();

        $ownApplication = $user
            ? ServerdemoValidatorApplication::where('user_id', $user->id)->latest()->first()
            : null;

        $canVote = $round !== null
            && $ownApplication !== null
            && in_array($ownApplication->status, self::VOTER_STATUSES, true);

        $myVotes = $canVote
            ? ServerdemoValidatorVote::where('round_id', $round->id)
                ->where('voter_id', $user->id)
                ->pluck('application_id')
                ->all()
            : [];

        return Inertia::render('ServerdemoValidators', [
            'application' => $ownApplication,
            'applicantCount' => ServerdemoValidatorApplication::open()->count(),
            'validators' => User::query()
                ->where('is_moderator', true)
                ->get()
                ->filter(fn (User $u) => $u->hasModeratorPermission('serverdemo_validation') && ! $u->isAdmin())
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->values(),

            'voting' => [
                'open' => $round !== null,
                'title' => $round?->title,
                'canVote' => $canVote,
                'myVotes' => $myVotes,
                // Tallies stay hidden while people are still voting - a
                // running score turns a vote into a bandwagon.
                'candidates' => $round === null ? [] : $this->candidates($user),
            ],
        ]);
    }

    /**
     * Who is on the ballot: every live application except the viewer's own.
     */
    private function candidates(?User $user)
    {
        return ServerdemoValidatorApplication::with('user')
            ->whereIn('status', self::CANDIDATE_STATUSES)
            ->when($user, fn ($q) => $q->where('user_id', '!=', $user->id))
            ->get()
            ->map(fn (ServerdemoValidatorApplication $a) => [
                'id' => $a->id,
                'name' => $a->user?->name,
                'user_id' => $a->user_id,
                'motivation' => $a->motivation,
                'experience' => $a->experience,
                'availability' => $a->availability,
            ])
            ->values();
    }

    public function apply(Request $request)
    {
        $user = $request->user();

        // One open application at a time. A rejected one may be replaced -
        // people improve - but a pending one cannot be spammed.
        if (ServerdemoValidatorApplication::where('user_id', $user->id)->open()->exists()) {
            return back()->withErrors([
                'motivation' => 'You already have an application in progress.',
            ]);
        }

        $data = $request->validate([
            'motivation' => ['required', 'string', 'min:60', 'max:4000'],
            'experience' => ['nullable', 'string', 'max:4000'],
            'availability' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
        ], [
            'motivation.min' => 'Tell us a bit more - a couple of sentences at least.',
        ]);

        ServerdemoValidatorApplication::create([
            ...$data,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Your application has been submitted.');
    }

    /**
     * Cast or take back a vote. Toggling rather than a separate delete route:
     * the button in front of the user is one button.
     */
    public function vote(Request $request, ServerdemoValidatorApplication $application)
    {
        $user = $request->user();
        $round = ServerdemoValidatorVoteRound::current();

        if ($round === null) {
            return back()->withErrors(['vote' => 'Voting is not open.']);
        }

        $own = ServerdemoValidatorApplication::where('user_id', $user->id)
            ->whereIn('status', self::VOTER_STATUSES)
            ->first();

        if ($own === null) {
            return back()->withErrors(['vote' => 'Only applicants vote in this round.']);
        }

        if ($application->user_id === $user->id) {
            return back()->withErrors(['vote' => 'You cannot vote for yourself.']);
        }

        if (! in_array($application->status, self::CANDIDATE_STATUSES, true)) {
            return back()->withErrors(['vote' => 'That application is not on the ballot.']);
        }

        $existing = ServerdemoValidatorVote::where('round_id', $round->id)
            ->where('voter_id', $user->id)
            ->where('application_id', $application->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return back()->with('success', 'Vote taken back.');
        }

        ServerdemoValidatorVote::create([
            'round_id' => $round->id,
            'application_id' => $application->id,
            'voter_id' => $user->id,
        ]);

        return back()->with('success', 'Vote cast.');
    }
}
