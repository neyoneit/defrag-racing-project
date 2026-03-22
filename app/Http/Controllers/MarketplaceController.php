<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceListing;
use App\Models\MarketplaceReview;
use App\Models\MarketplaceCreatorProfile;
use App\Models\Record;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        $canPost = false;
        $recordCount = 0;
        if (auth()->check()) {
            $recordCount = Cache::remember('marketplace:records:' . auth()->id(), 3600, function () {
                return Record::where('user_id', auth()->id())->count();
            });
            $canPost = $recordCount >= 50;
        }

        if (!$isPartial) {
            return Inertia::render('Marketplace/Index', [
                'listings' => null,
                'filters' => $request->only(['tab', 'work_type', 'status', 'search']),
                'canPost' => $canPost,
                'recordCount' => $recordCount,
            ]);
        }

        $tab = $request->input('tab', 'requests');

        $query = MarketplaceListing::with(['user', 'assignedTo'])
            ->withCount('reviews')
            ->when($request->work_type, fn($q) => $q->where('work_type', $request->work_type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"));

        if ($tab === 'offers') {
            $query->offers();
        } else {
            $query->requests();
        }

        $listings = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Marketplace/Index', [
            'listings' => $listings,
            'filters' => $request->only(['tab', 'work_type', 'status', 'search']),
            'canPost' => $canPost,
            'recordCount' => $recordCount,
        ]);
    }

    public function show(MarketplaceListing $listing)
    {
        $listing->load(['user', 'assignedTo', 'reviews.reviewer', 'reviews.reviewee']);

        $canReview = false;
        $userReview = null;
        if (auth()->check() && $listing->status === 'completed') {
            $userId = auth()->id();
            // Can review if you're the listing owner or the assigned user, and haven't reviewed yet
            if ($userId === $listing->user_id || $userId === $listing->assigned_to_user_id) {
                $userReview = MarketplaceReview::where('listing_id', $listing->id)
                    ->where('reviewer_id', $userId)
                    ->first();
                $canReview = !$userReview;
            }
        }

        // Get avg rating for listing owner and assigned user
        $ownerRating = MarketplaceReview::where('reviewee_id', $listing->user_id)->avg('rating');
        $assignedRating = $listing->assigned_to_user_id
            ? MarketplaceReview::where('reviewee_id', $listing->assigned_to_user_id)->avg('rating')
            : null;

        return Inertia::render('Marketplace/Show', [
            'listing' => $listing,
            'canReview' => $canReview,
            'userReview' => $userReview,
            'ownerRating' => $ownerRating ? round($ownerRating, 1) : null,
            'assignedRating' => $assignedRating ? round($assignedRating, 1) : null,
        ]);
    }

    public function createListing()
    {
        $userRecordsCount = Record::where('user_id', auth()->id())->count();

        if ($userRecordsCount < 50) {
            return redirect()->route('marketplace.index')
                ->withDanger("You need at least 50 records to post on the Marketplace. You currently have {$userRecordsCount}.");
        }

        return Inertia::render('Marketplace/Create');
    }

    public function storeListing(Request $request)
    {
        $userRecordsCount = Record::where('user_id', auth()->id())->count();

        if ($userRecordsCount < 50) {
            return redirect()->back()
                ->withDanger("You need at least 50 records to post on the Marketplace. You currently have {$userRecordsCount}.");
        }

        $validated = $request->validate([
            'listing_type' => 'required|in:request,offer',
            'work_type' => 'required|in:map,player_model,weapon_model,shadow_model',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'budget' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = auth()->id();

        $listing = MarketplaceListing::create($validated);

        return redirect()->route('marketplace.show', $listing)
            ->withSuccess('Listing created successfully!');
    }

    public function updateListingStatus(Request $request, MarketplaceListing $listing)
    {
        if ($listing->user_id !== auth()->id()) {
            return redirect()->back()->withDanger('You can only update your own listings.');
        }

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,completed,cancelled',
        ]);

        $listing->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'completed' ? now() : $listing->completed_at,
            'cancelled_at' => $validated['status'] === 'cancelled' ? now() : $listing->cancelled_at,
        ]);

        return redirect()->back()->withSuccess('Listing status updated.');
    }

    public function assignListing(Request $request, MarketplaceListing $listing)
    {
        if ($listing->status !== 'open') {
            return redirect()->back()->withDanger('This listing is no longer open.');
        }

        if ($listing->user_id === auth()->id()) {
            return redirect()->back()->withDanger('You cannot take your own listing.');
        }

        $userRecordsCount = Record::where('user_id', auth()->id())->count();
        if ($userRecordsCount < 50) {
            return redirect()->back()
                ->withDanger("You need at least 50 records to take commissions. You currently have {$userRecordsCount}.");
        }

        $listing->update([
            'assigned_to_user_id' => auth()->id(),
            'status' => 'in_progress',
        ]);

        // Notify listing owner
        $listing->user->systemNotify(
            'marketplace',
            'Someone took your commission',
            auth()->user()->name . ' has accepted your listing "' . $listing->title . '".',
            route('marketplace.show', $listing)
        );

        return redirect()->back()->withSuccess('You have taken this commission!');
    }

    public function storeReview(Request $request, MarketplaceListing $listing)
    {
        if ($listing->status !== 'completed') {
            return redirect()->back()->withDanger('You can only review completed listings.');
        }

        $userId = auth()->id();

        // Must be owner or assigned user
        if ($userId !== $listing->user_id && $userId !== $listing->assigned_to_user_id) {
            return redirect()->back()->withDanger('You are not part of this commission.');
        }

        // Check if already reviewed
        $existing = MarketplaceReview::where('listing_id', $listing->id)
            ->where('reviewer_id', $userId)
            ->exists();

        if ($existing) {
            return redirect()->back()->withDanger('You have already reviewed this listing.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        // Reviewee is the other party
        $revieweeId = $userId === $listing->user_id
            ? $listing->assigned_to_user_id
            : $listing->user_id;

        if (!$revieweeId) {
            return redirect()->back()->withDanger('Cannot review - no other party assigned.');
        }

        MarketplaceReview::create([
            'listing_id' => $listing->id,
            'reviewer_id' => $userId,
            'reviewee_id' => $revieweeId,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        return redirect()->back()->withSuccess('Review submitted!');
    }

    public function creators(Request $request)
    {
        $creators = MarketplaceCreatorProfile::where('is_listed', true)
            ->with('user')
            ->when($request->specialty, function ($q) use ($request) {
                $q->whereJsonContains('specialties', $request->specialty);
            })
            ->get()
            ->map(function ($profile) {
                $avgRating = MarketplaceReview::where('reviewee_id', $profile->user_id)->avg('rating');
                $reviewCount = MarketplaceReview::where('reviewee_id', $profile->user_id)->count();
                $profile->avg_rating = $avgRating ? round($avgRating, 1) : null;
                $profile->review_count = $reviewCount;
                $profile->featured_maps = $profile->featuredMaps()->select('id', 'name', 'thumbnail', 'author')->get();
                return $profile;
            });

        if ($request->sort === 'rating') {
            $creators = $creators->sortByDesc('avg_rating')->values();
        } else {
            $creators = $creators->sortByDesc('created_at')->values();
        }

        return Inertia::render('Marketplace/Creators', [
            'creators' => $creators,
            'filters' => $request->only(['specialty', 'sort']),
        ]);
    }

    public function creatorProfile(User $user)
    {
        $profile = MarketplaceCreatorProfile::where('user_id', $user->id)->firstOrFail();

        $avgRating = MarketplaceReview::where('reviewee_id', $user->id)->avg('rating');
        $reviewCount = MarketplaceReview::where('reviewee_id', $user->id)->count();
        $reviews = MarketplaceReview::where('reviewee_id', $user->id)
            ->with('reviewer', 'listing')
            ->latest()
            ->limit(20)
            ->get();

        $listings = MarketplaceListing::where('user_id', $user->id)
            ->withCount('reviews')
            ->latest()
            ->limit(10)
            ->get();

        $featuredMaps = $profile->featuredMaps()->select('id', 'name', 'thumbnail', 'author')->get();

        return Inertia::render('Marketplace/CreatorProfile', [
            'user' => $user,
            'profile' => $profile,
            'avgRating' => $avgRating ? round($avgRating, 1) : null,
            'reviewCount' => $reviewCount,
            'reviews' => $reviews,
            'listings' => $listings,
            'featuredMaps' => $featuredMaps,
        ]);
    }
}
