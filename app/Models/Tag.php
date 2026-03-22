<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'category',
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
        $displayName = trim($tagName);

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
