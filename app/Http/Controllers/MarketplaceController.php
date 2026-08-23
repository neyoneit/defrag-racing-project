<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceListing;
use App\Models\MarketplaceWorkType;
use App\Models\MarketplaceReview;
use App\Models\MarketplaceCreatorProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    /** What the picker sends when the four types do not fit. */
    private const OTHER = '__other__';

    private const MAX_PENDING_SUGGESTIONS = 3;

    /**
     * What `status=` may say. `active` is the default and the only one that
     * covers more than one state: it is what somebody comes here to find, and
     * a board led by cancelled work reads as a dead board.
     */
    private const STATUS_SETS = [
        'active' => ['open', 'in_progress'],
        'open' => ['open'],
        'in_progress' => ['in_progress'],
        'completed' => ['completed'],
        'cancelled' => ['cancelled'],
        'all' => ['open', 'in_progress', 'completed', 'cancelled'],
    ];

    private const DEFAULT_STATUS = 'active';

    public function index(Request $request)
    {
        $tab = $request->input('tab') === 'offers' ? 'offers' : 'requests';
        $status = array_key_exists((string) $request->input('status'), self::STATUS_SETS)
            ? (string) $request->input('status')
            : self::DEFAULT_STATUS;

        $filters = [
            'tab' => $tab,
            'work_type' => (string) $request->input('work_type', ''),
            'status' => $status,
            'search' => trim((string) $request->input('search', '')),
        ];

        // The page used to render with no listings at all and fetch them in a
        // second request the moment it mounted. Switching a tab is not a
        // mount, so it left the board blank until somebody pressed F5 - and
        // every page of the pagination did the same. There are twenty rows on
        // a page and the query is a couple of milliseconds, so they are simply
        // part of the page now.
        $listings = $this->listingsQuery($filters)->paginate(20)->withQueryString();

        return Inertia::render('Marketplace/Index', [
            'listings' => $listings,
            // What is on the other tab, so the tabs say it rather than making
            // somebody click to find out. Both counts follow the filters, so
            // they agree with what a click actually shows.
            'counts' => $this->tabCounts($filters),
            'filters' => $filters,
            'canPost' => auth()->check() && auth()->user()->mdd_id,
            'workTypes' => MarketplaceWorkType::options(),
            'openCreate' => $request->boolean('create'),
        ]);
    }

    /** One place the tab, the type, the status and the search are applied. */
    private function listingsQuery(array $filters)
    {
        $query = MarketplaceListing::with(['user', 'assignedTo'])
            ->withCount('reviews')
            ->whereIn('status', self::STATUS_SETS[$filters['status']])
            ->when($filters['work_type'] !== '', fn($q) => $q->where('work_type', $filters['work_type']))
            // Title and description both, because a request is often named
            // after the map and described by what is wanted.
            ->when($filters['search'] !== '', fn($q) => $q->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            }))
            ->latest();

        return $filters['tab'] === 'offers' ? $query->offers() : $query->requests();
    }

    /**
     * How many listings each tab holds under the current filters, so the tab
     * you are not on still says what is waiting there.
     */
    private function tabCounts(array $filters): array
    {
        return [
            'requests' => $this->listingsQuery(['tab' => 'requests'] + $filters)->count(),
            'offers' => $this->listingsQuery(['tab' => 'offers'] + $filters)->count(),
        ];
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

    /**
     * Posting is a dialog on the marketplace itself now. The route stays so
     * that a link somebody saved still lands somewhere useful.
     */
    public function createListing()
    {
        if (!auth()->user()->mdd_id) {
            return redirect()->route('marketplace.index')
                ->withDanger('You need to link your account to post on the Marketplace.');
        }

        return redirect()->route('marketplace.index', ['create' => 1]);
    }

    public function storeListing(Request $request)
    {
        if (!auth()->user()->mdd_id) {
            return redirect()->back()
                ->withDanger('You need to link your account to post on the Marketplace.');
        }

        $validated = $request->validate([
            'listing_type' => 'required|in:request,offer',
            'work_type' => ['required', 'string', Rule::in([...MarketplaceListing::workTypes(), self::OTHER])],
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'budget' => 'nullable|string|max:255',

            // Only read when the picker says "Something else".
            'custom_label' => 'required_if:work_type,' . self::OTHER . '|nullable|string|max:60',
            'custom_description' => 'nullable|string|max:160',
            'custom_label_local' => 'nullable|string|max:60',
        ]);

        if ($validated['work_type'] === self::OTHER) {
            $type = $this->workTypeFromSuggestion($validated);

            if (is_string($type)) {
                return redirect()->back()->withDanger($type)->withInput();
            }

            $validated['work_type'] = $type->slug;
        }

        $validated['user_id'] = auth()->id();

        $listing = MarketplaceListing::create($validated);

        return redirect()->route('marketplace.show', $listing)
            ->withSuccess('Listing created successfully!');
    }

    /**
     * A work type nobody had thought of yet. It is used straight away, so the
     * listing reads correctly the moment it is posted, and it is marked
     * pending until an admin confirms the wording and fills in the languages
     * the author did not.
     *
     * Returns the type, or a message explaining why not.
     */
    private function workTypeFromSuggestion(array $input): MarketplaceWorkType|string
    {
        $label = trim($input['custom_label']);

        // Somebody has already asked for this one. Reuse it rather than
        // growing two spellings of the same thing.
        $existing = MarketplaceWorkType::whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->where('status', '!=', 'rejected')
            ->first();

        if ($existing) {
            return $existing;
        }

        $pending = MarketplaceWorkType::where('suggested_by_user_id', auth()->id())
            ->where('status', 'pending')
            ->count();

        if ($pending >= self::MAX_PENDING_SUGGESTIONS) {
            return 'You already have ' . self::MAX_PENDING_SUGGESTIONS
                . ' work types waiting to be approved. Please wait for those first.';
        }

        $locale = app()->getLocale();
        $translations = [];

        // The author's own language, if they filled it in and it is not the
        // language the label is already written in.
        if ($locale !== 'en' && !empty($input['custom_label_local'])) {
            $translations[$locale] = ['label' => trim($input['custom_label_local'])];
        }

        return MarketplaceWorkType::create([
            'slug' => MarketplaceWorkType::slugFor($label),
            'label' => $label,
            'description' => !empty($input['custom_description']) ? trim($input['custom_description']) : null,
            'translations' => $translations ?: null,
            'color' => 'gray',
            'status' => 'pending',
            'sort_order' => 500,
            'suggested_by_user_id' => auth()->id(),
        ]);
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

        if (!auth()->user()->mdd_id) {
            return redirect()->back()
                ->withDanger('You need to link your account to take commissions.');
        }

        $listing->update([
            'assigned_to_user_id' => auth()->id(),
            'status' => 'in_progress',
        ]);

        // Notify listing owner. systemNotify takes five arguments
        // (type, before, headline, after, url) and before/after are NOT NULL
        // in the database, so they are passed as empty strings rather than
        // left out.
        $listing->user->systemNotify(
            'marketplace',
            '',
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
            ->withSomethingToShow()
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
            'workTypes' => MarketplaceWorkType::options(),
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
            'workTypes' => MarketplaceWorkType::options(),
        ]);
    }
}
