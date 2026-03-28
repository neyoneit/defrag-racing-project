<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\ModerationLog;

class Tag extends Model
{
    protected $casts = [
        'blocked_keywords' => 'array',
    ];

    /** Set to true to skip ModerationLog/TagActivity in deleting event (e.g. during merge) */
    public bool $skipDeleteLog = false;

    protected static function booted()
    {
        static::deleting(function (Tag $tag) {
            if ($tag->skipDeleteLog) return;

            // Collect map IDs before cascade delete removes them
            $mapIds = $tag->maps()->pluck('maps.id')->toArray();
            $maplistIds = $tag->maplists()->pluck('maplists.id')->toArray();

            ModerationLog::log('tags', 'deleted', $tag, [
                'tag_name' => $tag->display_name,
                'tag_name_normalized' => $tag->name,
                'usage_count' => $tag->usage_count,
                'category' => $tag->category,
                'note' => $tag->note,
                'blocked_keywords' => $tag->blocked_keywords,
                'youtube_url' => $tag->youtube_url,
                'parent_tag_id' => $tag->parent_tag_id,
                'map_ids' => $mapIds,
                'maplist_ids' => $maplistIds,
                'map_count' => count($mapIds),
            ]);

            TagActivity::log('deleted', auth()?->id() ?? 0, $tag->id, 'delete', 0, [
                'tag_name' => $tag->display_name,
                'tag_name_normalized' => $tag->name,
                'usage_count' => $tag->usage_count,
                'category' => $tag->category,
                'note' => $tag->note,
                'blocked_keywords' => $tag->blocked_keywords,
                'youtube_url' => $tag->youtube_url,
                'parent_tag_id' => $tag->parent_tag_id,
                'map_ids' => $mapIds,
                'maplist_ids' => $maplistIds,
            ]);
        });
    }

    protected $fillable = [
        'name',
        'display_name',
        'category',
        'note',
        'blocked_keywords',
        'youtube_url',
        'usage_count',
        'parent_tag_id',
    ];

    /**
     * Maps that have this tag
     */
    public function maps(): BelongsToMany
    {
        return $this->belongsToMany(Map::class, 'map_tag')
            ->withPivot('user_id')
            ->withTimestamps();
    }

    /**
     * Parent tag (e.g. "Ground Boost" is parent of "PGB")
     */
    public function parent()
    {
        return $this->belongsTo(Tag::class, 'parent_tag_id');
    }

    /**
     * Child tags (e.g. "PGB" and "RGB" are children of "Ground Boost")
     */
    public function children()
    {
        return $this->hasMany(Tag::class, 'parent_tag_id');
    }

    /**
     * Maplists that have this tag
     */
    public function maplists(): BelongsToMany
    {
        return $this->belongsToMany(Maplist::class, 'maplist_tag')
            ->withPivot('user_id')
            ->withTimestamps();
    }

    /**
     * Get or create a tag by name (case-insensitive)
     */
    public static function findOrCreateByName(string $tagName, ?string $category = null): Tag
    {
        $normalized = strtolower(trim($tagName));
        $displayName = ucfirst(strtolower(trim($tagName)));

        $tag = static::where('name', $normalized)->first();

        if (!$tag) {
            $tag = static::create([
                'name' => $normalized,
                'display_name' => $displayName,
                'category' => $category,
                'usage_count' => 0,
            ]);
        }

        return $tag;
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Decrement usage count
     */
    public function decrementUsage(): void
    {
        if ($this->usage_count > 0) {
            $this->decrement('usage_count');
        }
    }
}
