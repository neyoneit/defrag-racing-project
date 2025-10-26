<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tag;
use App\Models\Map;
use App\Models\Maplist;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    /**
     * Get all tags
     */
    public function index(Request $request)
    {
        $query = Tag::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('display_name', 'LIKE', "%{$search}%");
        }

        $tags = $query->orderBy('usage_count', 'desc')
                      ->orderBy('display_name')
                      ->get();

        return response()->json(['tags' => $tags]);
    }

    /**
     * Add tag to a map
     */
    public function addToMap(Request $request, $mapId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'tag_name' => 'required|string|max:50',
        ]);

        $map = Map::findOrFail($mapId);
        $tag = Tag::findOrCreateByName($validated['tag_name']);

        // Check if tag already exists on this map
        if ($map->tags()->where('tag_id', $tag->id)->exists()) {
            return response()->json(['error' => 'Tag already exists on this map'], 400);
        }

        // Attach tag to map
        $map->tags()->attach($tag->id, ['user_id' => Auth::id()]);
        $tag->incrementUsage();

        return response()->json([
            'message' => 'Tag added successfully',
            'tag' => $tag,
        ]);
    }

    /**
     * Remove tag from a map
     */
    public function removeFromMap($mapId, $tagId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $map = Map::findOrFail($mapId);
        $tag = Tag::findOrFail($tagId);

        $map->tags()->detach($tagId);
        $tag->decrementUsage();

        return response()->json(['message' => 'Tag removed successfully']);
    }

    /**
     * Add tag to a maplist
     */
    public function addToMaplist(Request $request, $maplistId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'tag_name' => 'required|string|max:50',
        ]);

        $maplist = Maplist::with('maps')->findOrFail($maplistId);

        // Only owner can add tags
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $tag = Tag::findOrCreateByName($validated['tag_name']);

        // Check if tag already exists on this maplist
        if ($maplist->tags()->where('tag_id', $tag->id)->exists()) {
            return response()->json(['error' => 'Tag already exists on this maplist'], 400);
        }

        // Attach tag to maplist
        $maplist->tags()->attach($tag->id, ['user_id' => Auth::id()]);
        $tag->incrementUsage();

        return response()->json([
            'message' => 'Tag added successfully',
            'tag' => $tag,
        ]);
    }

    /**
     * Remove tag from a maplist
     */
    public function removeFromMaplist($maplistId, $tagId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($maplistId);

        // Only owner can remove tags
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $tag = Tag::findOrFail($tagId);

        $maplist->tags()->detach($tagId);
        $tag->decrementUsage();

        return response()->json(['message' => 'Tag removed successfully']);
    }
}
