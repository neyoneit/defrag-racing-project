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
        $view = $request->get('view', 'public'); // 'public', 'mine', or 'favorites'

        $query = Maplist::with(['user', 'maps']);
        $playLater = null;

        // If viewing "favorites", show maplists the user has favorited
        if ($view === 'favorites' && Auth::check()) {
            $favoriteMaplistIds = \DB::table('maplist_favorites')
                ->where('user_id', Auth::id())
                ->pluck('maplist_id');

            $query->whereIn('id', $favoriteMaplistIds)
                  ->where('is_play_later', false);
        }
        // If viewing "mine", show user's own maplists
        elseif ($view === 'mine' && Auth::check()) {
            $query->where('user_id', Auth::id());

            // Get Play Later separately to show it first
            $playLater = Maplist::where('user_id', Auth::id())
                ->where('is_play_later', true)
                ->with(['user', 'maps'])
                ->first();

            // Exclude Play Later from main query since we'll prepend it
            $query->where('is_play_later', false);
        } elseif ($userId) {
            // If filtering by specific user
            $query->where('user_id', $userId);

            // Only show public maplists unless it's the current user viewing their own
            if (!Auth::check() || Auth::id() != $userId) {
                $query->where('is_public', true)
                      ->where('is_play_later', false);
            } else {
                // Get Play Later separately
                $playLater = Maplist::where('user_id', $userId)
                    ->where('is_play_later', true)
                    ->with(['user', 'maps'])
                    ->first();
                $query->where('is_play_later', false);
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

        // Prepend Play Later to the collection if it exists
        if ($playLater) {
            $maplists->getCollection()->prepend($playLater);
        }

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
            'view' => $view,
        ]);
    }

    /**
     * Show a single maplist
     */
    public function show($id)
    {
        $maplist = Maplist::with(['user', 'maps', 'tags'])->findOrFail($id);

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

        // Don't allow deleting if maplist has been favorited by anyone
        $favoritesCount = \DB::table('maplist_favorites')
            ->where('maplist_id', $maplist->id)
            ->count();

        if ($favoritesCount > 0) {
            return response()->json([
                'error' => 'Cannot delete maplist that has been favorited by users. This maplist has ' . $favoritesCount . ' favorite(s).'
            ], 400);
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

    /**
     * Create maplist with maps in bulk
     */
    public function createWithMaps(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'map_names' => 'array',
            'map_names.*' => 'string',
        ]);

        // Validate all map names first
        $mapNames = $validated['map_names'] ?? [];
        $errors = [];
        $validMapIds = [];

        if (!empty($mapNames)) {
            foreach ($mapNames as $mapName) {
                $map = Map::where('name', $mapName)->first();

                if (!$map) {
                    // Find suggestions (similar map names)
                    $suggestions = Map::where('name', 'LIKE', '%' . $mapName . '%')
                        ->orWhere('name', 'LIKE', str_replace(' ', '%', $mapName) . '%')
                        ->limit(5)
                        ->pluck('name')
                        ->toArray();

                    $errors[] = [
                        'map_name' => $mapName,
                        'message' => 'Map not found',
                        'suggestions' => $suggestions,
                    ];
                } else {
                    $validMapIds[] = $map->id;
                }
            }
        }

        // If any maps not found, return errors and don't create maplist
        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        // Create the maplist
        $maplist = Maplist::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'is_public' => $validated['is_public'] ?? true,
            'is_play_later' => false,
        ]);

        // Add maps to maplist with positions
        foreach ($validMapIds as $position => $mapId) {
            MaplistMap::create([
                'maplist_id' => $maplist->id,
                'map_id' => $mapId,
                'position' => $position,
            ]);
        }

        return response()->json([
            'message' => 'Maplist created successfully',
            'maplist' => $maplist,
        ]);
    }

    /**
     * Reorder maps in a maplist
     */
    public function reorderMaps(Request $request, $id)
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
            'map_ids' => 'required|array',
            'map_ids.*' => 'exists:maps,id',
        ]);

        // Update positions
        foreach ($validated['map_ids'] as $position => $mapId) {
            MaplistMap::where('maplist_id', $id)
                ->where('map_id', $mapId)
                ->update(['position' => $position]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    /**
     * Save or update a draft maplist
     */
    public function saveDraft(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'id' => 'nullable|exists:maplists,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'map_names' => 'nullable|string',
        ]);

        // If draft ID provided, update existing draft
        if (!empty($validated['id'])) {
            $maplist = Maplist::findOrFail($validated['id']);

            // Check ownership
            if ($maplist->user_id !== Auth::id()) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            // Only update if it's a draft
            if (!$maplist->is_draft) {
                return response()->json(['error' => 'Cannot update non-draft maplist'], 403);
            }

            $maplist->update([
                'name' => $validated['name'] ?? $maplist->name,
                'description' => $validated['description'] ?? $maplist->description,
            ]);

            // Store map names in description temporarily (we'll parse them on publish)
            // For now, just store the raw text
            if (isset($validated['map_names'])) {
                // Store map_names in a JSON field or separate table if needed
                // For simplicity, we'll just return success
            }

            return response()->json([
                'message' => 'Draft updated',
                'maplist' => $maplist,
            ]);
        }

        // Create new draft
        $maplist = Maplist::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'] ?? 'Untitled Draft',
            'description' => $validated['description'] ?? '',
            'is_public' => false,
            'is_play_later' => false,
            'is_draft' => true,
        ]);

        return response()->json([
            'message' => 'Draft created',
            'maplist' => $maplist,
        ]);
    }

    /**
     * Get user's draft maplists
     */
    public function getDrafts(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $drafts = Maplist::where('user_id', Auth::id())
            ->where('is_draft', true)
            ->with(['user', 'maps'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['drafts' => $drafts]);
    }

    /**
     * Delete a draft
     */
    public function deleteDraft($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Only delete if it's a draft
        if (!$maplist->is_draft) {
            return response()->json(['error' => 'Cannot delete non-draft maplist'], 403);
        }

        $maplist->delete();

        return response()->json(['message' => 'Draft deleted']);
    }
}
