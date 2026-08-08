<?php

namespace App\Http\Controllers;

use App\Models\Wish;
use App\Models\WishVote;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The wishlist. Anyone with an account writes down what they want built and
 * everyone else says whether they want it too.
 *
 * Sorted by score, not by date. A suggestion box ordered by recency rewards
 * whoever posted last; ordered by score it says what the site actually wants,
 * which is the only reason to have one of these.
 *
 * Downvotes count and are shown. A wish nobody else wants is worth knowing
 * about before it gets built, and hiding that behind an upvote-only counter
 * just moves the argument into the comments of something already started.
 */
class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $project = $request->input('project');

        // Anything that is not one of the four statuses means the open list,
        // which is where the list starts. Done and Not happening are answers,
        // not candidates, and they have a tab each.
        $filter = in_array($request->input('status'), array_keys(Wish::STATUSES), true)
            ? $request->input('status')
            : 'open';

        $statusScope = fn ($query) => $filter === 'open'
            ? $query->whereIn('status', Wish::OPEN_STATUSES)
            : $query->where('status', $filter);

        $wishes = Wish::query()
            ->with('user:id,name,plain_name,profile_photo_path,country')
            // Approved only, plus your own while it waits. Hiding somebody's
            // wish from themselves reads as it having been deleted, and they
            // post it again.
            ->where(function ($query) use ($user) {
                $query->approved();

                if ($user) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->tap($statusScope)
            ->when(in_array($project, array_keys(Wish::PROJECTS), true), fn ($q) => $q->where('project', $project))
            // Anything still waiting sits at the top for its author, who has
            // just posted it and is looking for it.
            ->orderByRaw('approved_at is null desc')
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        $myVotes = $user
            ? WishVote::where('user_id', $user->id)
                ->whereIn('wish_id', $wishes->pluck('id'))
                ->pluck('value', 'wish_id')
            : collect();

        return Inertia::render('Wishlist', [
            'wishes' => $wishes->map(fn (Wish $wish) => [
                'id' => $wish->id,
                'project' => $wish->project,
                'project_label' => Wish::PROJECTS[$wish->project] ?? $wish->project,
                'title' => $wish->title,
                'body' => $wish->body,
                'image_url' => $wish->imageUrl(),
                'youtube_id' => $wish->youtube_id,
                'status' => $wish->status,
                'status_label' => Wish::STATUSES[$wish->status] ?? $wish->status,
                'status_note' => $wish->status_note,
                'upvotes' => $wish->upvotes,
                'downvotes' => $wish->downvotes,
                'score' => $wish->score,
                'my_vote' => (int) ($myVotes[$wish->id] ?? 0),
                'mine' => $user && $wish->user_id === $user->id,
                'pending' => ! $wish->isApproved(),
                'removal_requested' => $wish->removal_requested_at !== null,
                'created_at' => $wish->created_at?->toIso8601String(),
                'author' => $wish->user ? [
                    'id' => $wish->user->id,
                    'name' => $wish->user->plain_name ?: $wish->user->name,
                    'profile_photo_path' => $wish->user->profile_photo_path,
                    'country' => $wish->user->country,
                ] : null,
            ]),
            'statuses' => Wish::STATUSES,
            'projects' => Wish::PROJECTS,
            // Shown on the page, always. A budget people only discover by
            // running out of it reads as the site being broken.
            'budget' => $user ? [
                'total' => Wish::voteBudget(),
                'spent' => Wish::upvotesSpentBy($user->id),
                'left' => Wish::upvotesLeftFor($user->id),
            ] : null,
            'filter' => $filter,
            'projectFilter' => in_array($project, array_keys(Wish::PROJECTS), true) ? $project : null,
            // Counts per project so the filter row can say where the activity
            // is, instead of sending people into empty lists to find out.
            // Counted under the status tab in play, or the sidebar would
            // promise wishes the list below it is not showing.
            'projectCounts' => Wish::approved()
                ->tap($statusScope)
                ->selectRaw('project, count(*) as total')
                ->groupBy('project')
                ->pluck('total', 'project'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project' => ['required', 'string', 'in:' . implode(',', array_keys(Wish::PROJECTS))],
            'title' => ['required', 'string', 'min:6', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            // Extensions rather than the `image` rule, which accepts svg -
            // an svg is a document that can carry script, and this one ends up
            // inlined on a page everybody reads.
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'youtube_url' => ['nullable', 'string', 'max:255'],
        ], [
            'body.min' => 'Say a bit more - at least 20 characters, so people know what they are voting on.',
            'image.mimes' => 'The screenshot has to be a jpg, png, webp or gif.',
            'image.max' => 'The screenshot has to be under 4 MB.',
        ]);

        $youtubeId = null;

        if (filled($data['youtube_url'] ?? null)) {
            $youtubeId = Wish::youtubeId($data['youtube_url']);

            if (! $youtubeId) {
                return back()->withErrors(['youtube_url' => 'That is not a YouTube link.'])->withInput();
            }
        }

        // Not approved: it goes nowhere public until an admin lets it through.
        $wish = Wish::create([
            'user_id' => $request->user()->id,
            'project' => $data['project'],
            'title' => $data['title'],
            'body' => $data['body'],
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store(Wish::IMAGE_DIR, Wish::IMAGE_DISK)
                : null,
            'youtube_id' => $youtubeId,
        ]);

        // The author wants it, obviously. Saying so explicitly means a fresh
        // wish starts at +1 instead of sitting at zero next to things people
        // have actively turned down.
        WishVote::create([
            'wish_id' => $wish->id,
            'user_id' => $request->user()->id,
            'value' => 1,
        ]);
        $wish->recount();

        return back()->with('success', 'Wish submitted. It goes on the list once an admin has looked at it.');
    }

    /**
     * One vote per person per wish. Clicking the side you already picked
     * takes the vote back, which is the only way to say "I no longer care"
     * without it counting as the opposite.
     *
     * Upvotes come out of a budget (see Wish::voteBudget). Taking a vote back
     * or flipping it to a downvote refunds it, so nobody is ever stuck with a
     * vote they no longer mean.
     */
    public function vote(Request $request, Wish $wish)
    {
        // Its own author can see it while it waits, so the guard has to be
        // here too rather than relying on the list not showing it.
        if (! $wish->isApproved()) {
            return back(303);
        }

        $data = $request->validate([
            'value' => ['required', 'integer', 'in:1,-1'],
        ]);

        $user = $request->user();

        $existing = WishVote::where('wish_id', $wish->id)
            ->where('user_id', $user->id)
            ->first();

        // Charged only when this click actually adds an upvote to somebody
        // else's wish: not when taking one back, not when flipping to a
        // downvote, not on your own.
        $spendsAVote = (int) $data['value'] === 1
            && $wish->user_id !== $user->id
            && (! $existing || $existing->value !== 1);

        if ($spendsAVote && Wish::upvotesLeftFor($user->id) < 1) {
            return back(303)->withErrors([
                'vote' => 'You have used all your votes. Take one back from another wish to free it up.',
            ]);
        }

        if ($existing && $existing->value === (int) $data['value']) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['value' => (int) $data['value']]);
        } else {
            WishVote::create([
                'wish_id' => $wish->id,
                'user_id' => $user->id,
                'value' => (int) $data['value'],
            ]);
        }

        $wish->recount();

        return back(303);
    }

    /**
     * Ask for your wish to be taken down. Only asking: once it is on the list
     * other people have voted on it, and an author who could delete at will
     * could withdraw an idea the moment it started collecting downvotes.
     */
    public function requestRemoval(Request $request, Wish $wish)
    {
        if ($wish->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $wish->update([
            'removal_requested_at' => now(),
            'removal_reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Asked the admin to remove it. It stays on the list until they do.');
    }
}
