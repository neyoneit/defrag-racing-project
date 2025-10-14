<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Maplist;
use App\Models\MaplistMap;
use App\Models\MaplistLike;
use App\Models\MaplistFavorite;
use App\Models\Map;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MaplistController extends Controller
{
    /**
     * Display a listing of maplists (public, sorted by likes/favorites)
     */
    public function index(Request $request)
    {
        $sort = $request->get('sort', 'likes'); // 'likes' or 'favorites'
        $userId = $request->get('user'); // Filter by user

        $query = Maplist::with(['user', 'maps']);

        // If filtering by user, show their maplists (public and private if it's the current user)
        if ($userId) {
            $query->where('user_id', $userId);

            // Only show public maplists unless it's the current user viewing their own
            if (!Auth::check() || Auth::id() != $userId) {
                $query->where('is_public', true);
            }
        } else {
            // Public browse mode - hide Play Later maplists
            $query->where('is_public', true)
                  ->where('is_play_later', false);
        }

        if ($sort === 'favorites') {
            $query->orderBy('favorites_count', 'desc');
        } else {
            $query->orderBy('likes_count', 'desc');
        }

        $maplists = $query->paginate(20);

        // Add user interaction status if authenticated
        if (Auth::check()) {
            $maplists->getCollection()->transform(function ($maplist) {
                $maplist->is_liked = $maplist->isLikedBy(Auth::id());
                $maplist->is_favorited = $maplist->isFavoritedBy(Auth::id());
                return $maplist;
            });
        }

        return Inertia::render('Maplists/Index', [
            'maplists' => $maplists,
            'sort' => $sort,
            'user_id' => $userId,
        ]);
    }

    /**
     * Show a single maplist
     */
    public function show($id)
    {
        $maplist = Maplist::with(['user', 'maps'])->findOrFail($id);

        // Check if user can view this maplist
        if (!$maplist->is_public && (!Auth::check() || Auth::id() !== $maplist->user_id)) {
            abort(403, 'This maplist is private');
        }

        $isLiked = Auth::check() ? $maplist->isLikedBy(Auth::id()) : false;
        $isFavorited = Auth::check() ? $maplist->isFavoritedBy(Auth::id()) : false;

        // Fetch servers for Play Later functionality
        $servers = [];
        if ($maplist->is_play_later && Auth::check() && Auth::id() === $maplist->user_id) {
            $servers = \App\Models\Server::where('online', true)
                ->where('visible', true)
                ->with('onlinePlayers')
                ->orderBy('plain_name', 'asc')
                ->get()
                ->map(function ($server) {
                    return [
                        'id' => $server->id,
                        'name' => $server->plain_name,
                        'address' => $server->ip,
                        'port' => $server->port,
                        'players_current' => $server->onlinePlayers->count(),
                        'players_max' => 64, // Default max players
                        'location' => $server->location,
                    ];
                });
        }

        return Inertia::render('Maplists/Show', [
            'maplist' => $maplist,
            'is_liked' => $isLiked,
            'is_favorited' => $isFavorited,
            'is_owner' => Auth::check() && Auth::id() === $maplist->user_id,
            'servers' => $servers,
        ]);
    }

    /**
     * Get user's maplists (for adding maps to maplist)
     */
    public function getUserMaplists()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplists = Maplist::where('user_id', Auth::id())
            ->withCount('maps')
            ->orderBy('is_play_later', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($maplists);
    }

    /**
     * Create a new maplist
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $maplist = Maplist::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
            'is_play_later' => false,
        ]);

        return response()->json([
            'message' => 'Maplist created successfully',
            'maplist' => $maplist,
        ], 201);
    }

    /**
     * Update a maplist
     */
    public function update(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
        ]);

        // Don't allow changing "Play Later" maplist name
        if ($maplist->is_play_later && isset($validated['name'])) {
            unset($validated['name']);
        }

        $maplist->update($validated);

        return response()->json([
            'message' => 'Maplist updated successfully',
            'maplist' => $maplist,
        ]);
    }

    /**
     * Delete a maplist
     */
    public function destroy($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Don't allow deleting "Play Later" maplist
        if ($maplist->is_play_later) {
            return response()->json(['error' => 'Cannot delete Play Later maplist'], 400);
        }

        $maplist->delete();

        return response()->json([
            'message' => 'Maplist deleted successfully',
        ]);
    }

    /**
     * Add a map to a maplist
     */
    public function addMap(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'map_id' => 'required|exists:maps,id',
        ]);

        // Check if map already exists in maplist
        $exists = MaplistMap::where('maplist_id', $id)
            ->where('map_id', $validated['map_id'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Map already in maplist'], 400);
        }

        // Get the max position
        $maxPosition = MaplistMap::where('maplist_id', $id)->max('position') ?? -1;

        MaplistMap::create([
            'maplist_id' => $id,
            'map_id' => $validated['map_id'],
            'position' => $maxPosition + 1,
        ]);

        return response()->json([
            'message' => 'Map added to maplist successfully',
        ]);
    }

    /**
     * Remove a map from a maplist
     */
    public function removeMap($maplistId, $mapId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($maplistId);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        MaplistMap::where('maplist_id', $maplistId)
            ->where('map_id', $mapId)
            ->delete();

        return response()->json([
            'message' => 'Map removed from maplist successfully',
        ]);
    }

    /**
     * Toggle like on a maplist
     */
    public function toggleLike($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check if maplist is public
        if (!$maplist->is_public) {
            return response()->json(['error' => 'Cannot like private maplists'], 400);
        }

        $like = MaplistLike::where('user_id', Auth::id())
            ->where('maplist_id', $id)
            ->first();

        if ($like) {
            // Unlike
            $like->delete();
            $maplist->decrement('likes_count');
            $isLiked = false;
        } else {
            // Like
            MaplistLike::create([
                'user_id' => Auth::id(),
                'maplist_id' => $id,
            ]);
            $maplist->increment('likes_count');
            $isLiked = true;
        }

        return response()->json([
            'message' => $isLiked ? 'Maplist liked' : 'Maplist unliked',
            'is_liked' => $isLiked,
            'likes_count' => $maplist->fresh()->likes_count,
        ]);
    }

    /**
     * Toggle favorite on a maplist
     */
    public function toggleFavorite($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check if maplist is public
        if (!$maplist->is_public) {
            return response()->json(['error' => 'Cannot favorite private maplists'], 400);
        }

        $favorite = MaplistFavorite::where('user_id', Auth::id())
            ->where('maplist_id', $id)
            ->first();

        if ($favorite) {
            // Unfavorite
            $favorite->delete();
            $maplist->decrement('favorites_count');
            $isFavorited = false;
        } else {
            // Favorite
            MaplistFavorite::create([
                'user_id' => Auth::id(),
                'maplist_id' => $id,
            ]);
            $maplist->increment('favorites_count');
            $isFavorited = true;
        }

        return response()->json([
            'message' => $isFavorited ? 'Maplist favorited' : 'Maplist unfavorited',
            'is_favorited' => $isFavorited,
            'favorites_count' => $maplist->fresh()->favorites_count,
        ]);
    }

    /**
     * Search maps (for adding to maplist)
     */
    public function searchMaps(Request $request)
    {
        $query = $request->get('q', '');

        $maps = Map::where('name', 'like', '%' . $query . '%')
            ->orWhere('author', 'like', '%' . $query . '%')
            ->limit(20)
            ->get(['id', 'name', 'author', 'thumbnail']);

        return response()->json($maps);
    }
}
