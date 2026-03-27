<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tag;
use App\Models\TagActivity;
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

        $tags = $query->with(['parent:id,name,display_name', 'children:id,name,display_name,parent_tag_id'])
                      ->orderBy('usage_count', 'desc')
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

        if (Auth::user()->tag_banned) {
            return response()->json(['error' => 'You have been banned from adding or removing tags.'], 403);
        }

        $validated = $request->validate([
            'tag_name' => 'required|string|max:50',
        ]);

        $map = Map::findOrFail($mapId);

        // Check if typed name matches any tag's blocked keywords
        $normalizedInput = strtolower(trim($validated['tag_name']));
        $blockedMatch = Tag::whereNotNull('blocked_keywords')
            ->get()
            ->first(function ($t) use ($normalizedInput) {
                $keywords = is_array($t->blocked_keywords) ? $t->blocked_keywords : [];
                foreach ($keywords as $kw) {
                    if (strtolower(trim($kw)) === $normalizedInput) {
                        return true;
                    }
                }
                return false;
            });

        if ($blockedMatch) {
            return response()->json([
                'error' => 'blocked_keyword',
                'message' => "This keyword is blocked. Use the correct tag instead.",
                'suggested_tag' => $blockedMatch->load('parent:id,name,display_name', 'children:id,name,display_name,parent_tag_id'),
            ], 422);
        }

        $tag = Tag::findOrCreateByName($validated['tag_name']);

        // Check if tag already exists on this map
        if ($map->tags()->where('tag_id', $tag->id)->exists()) {
            return response()->json(['error' => 'Tag already exists on this map'], 400);
        }

        // Attach tag to map
        $map->tags()->attach($tag->id, ['user_id' => Auth::id()]);
        $tag->incrementUsage();
        TagActivity::log('added', Auth::id(), $tag->id, Map::class, $map->id);

        // Auto-attach parent tag if this is a child tag
        $parentTagAdded = null;
        if ($tag->parent_tag_id) {
            $parentTag = Tag::find($tag->parent_tag_id);
            if ($parentTag && !$map->tags()->where('tag_id', $parentTag->id)->exists()) {
                $map->tags()->attach($parentTag->id, ['user_id' => Auth::id()]);
                $parentTag->incrementUsage();
                TagActivity::log('added', Auth::id(), $parentTag->id, Map::class, $map->id, ['auto_parent' => true]);
                $parentTagAdded = $parentTag;
            }
        }

        return response()->json([
            'message' => 'Tag added successfully',
            'tag' => $tag->load('parent:id,name,display_name', 'children:id,name,display_name,parent_tag_id'),
            'parent_tag_added' => $parentTagAdded,
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

        if (Auth::user()->tag_banned) {
            return response()->json(['error' => 'You have been banned from adding or removing tags.'], 403);
        }

        $map = Map::findOrFail($mapId);
        $tag = Tag::findOrFail($tagId);

        $map->tags()->detach($tagId);
        $tag->decrementUsage();
        TagActivity::log('removed', Auth::id(), $tag->id, Map::class, $map->id);

        // If removing a parent tag, also remove its children from this map
        $removedChildIds = [];
        $children = $tag->children;
        foreach ($children as $child) {
            if ($map->tags()->where('tag_id', $child->id)->exists()) {
                $map->tags()->detach($child->id);
                $child->decrementUsage();
                TagActivity::log('removed', Auth::id(), $child->id, Map::class, $map->id, ['auto_child' => true]);
                $removedChildIds[] = $child->id;
            }
        }

        return response()->json([
            'message' => 'Tag removed successfully',
            'removed_child_ids' => $removedChildIds,
        ]);
    }

    /**
     * Add tag to a maplist
     */
    public function addToMaplist(Request $request, $maplistId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (Auth::user()->tag_banned) {
            return response()->json(['error' => 'You have been banned from adding or removing tags.'], 403);
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
        TagActivity::log('added', Auth::id(), $tag->id, Maplist::class, $maplist->id);

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

        if (Auth::user()->tag_banned) {
            return response()->json(['error' => 'You have been banned from adding or removing tags.'], 403);
        }

        $maplist = Maplist::findOrFail($maplistId);

        // Only owner can remove tags
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $tag = Tag::findOrFail($tagId);

        $maplist->tags()->detach($tagId);
        $tag->decrementUsage();
        TagActivity::log('removed', Auth::id(), $tag->id, Maplist::class, $maplist->id);

        return response()->json(['message' => 'Tag removed successfully']);
    }
}
