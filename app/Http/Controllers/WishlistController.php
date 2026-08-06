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
        $filter = $request->input('status');

        $wishes = Wish::query()
            ->with('user:id,name,plain_name,profile_photo_path,country')
            ->when(in_array($filter, array_keys(Wish::STATUSES), true), fn ($q) => $q->where('status', $filter))
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
                'title' => $wish->title,
                'body' => $wish->body,
                'status' => $wish->status,
                'status_label' => Wish::STATUSES[$wish->status] ?? $wish->status,
                'status_note' => $wish->status_note,
                'upvotes' => $wish->upvotes,
                'downvotes' => $wish->downvotes,
                'score' => $wish->score,
                'my_vote' => (int) ($myVotes[$wish->id] ?? 0),
                'mine' => $user && $wish->user_id === $user->id,
                'created_at' => $wish->created_at?->toIso8601String(),
                'author' => $wish->user ? [
                    'id' => $wish->user->id,
                    'name' => $wish->user->plain_name ?: $wish->user->name,
                    'profile_photo_path' => $wish->user->profile_photo_path,
                    'country' => $wish->user->country,
                ] : null,
            ]),
            'statuses' => Wish::STATUSES,
            'filter' => $filter,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:6', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'body.min' => 'Say a bit more - at least 20 characters, so people know what they are voting on.',
        ]);

        $wish = Wish::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
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

        return back()->with('success', 'Wish added.');
    }

    /**
     * One vote per person per wish. Clicking the side you already picked
     * takes the vote back, which is the only way to say "I no longer care"
     * without it counting as the opposite.
     */
    public function vote(Request $request, Wish $wish)
    {
        $data = $request->validate([
            'value' => ['required', 'integer', 'in:1,-1'],
        ]);

        $existing = WishVote::where('wish_id', $wish->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing && $existing->value === (int) $data['value']) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['value' => (int) $data['value']]);
        } else {
            WishVote::create([
                'wish_id' => $wish->id,
                'user_id' => $request->user()->id,
                'value' => (int) $data['value'],
            ]);
        }

        $wish->recount();

        return back(303);
    }

    /** Authors can withdraw their own wish; anything else is the admin's. */
    public function destroy(Request $request, Wish $wish)
    {
        if ($wish->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }

        $wish->delete();

        return back()->with('success', 'Wish removed.');
    }
}
