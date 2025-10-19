<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Maplist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_public',
        'is_play_later',
        'likes_count',
        'favorites_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_play_later' => 'boolean',
    ];

    protected $appends = [
        'maps_count'
    ];

    /**
     * Get the user that owns the maplist
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the maps in this maplist
     */
    public function maps()
    {
        return $this->belongsToMany(Map::class, 'maplist_maps')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('maplist_maps.position');
    }

    /**
     * Get the maplist_maps pivot records
     */
    public function maplistMaps()
    {
        return $this->hasMany(MaplistMap::class)->orderBy('position');
    }

    /**
     * Get users who liked this maplist
     */
    public function likes()
    {
        return $this->belongsToMany(User::class, 'maplist_likes')
            ->withTimestamps();
    }

    /**
     * Get users who favorited this maplist
     */
    public function favorites()
    {
        return $this->belongsToMany(User::class, 'maplist_favorites')
            ->withTimestamps();
    }

    /**
     * Check if a user has liked this maplist
     */
    public function isLikedBy($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    /**
     * Check if a user has favorited this maplist
     */
    public function isFavoritedBy($userId)
    {
        return $this->favorites()->where('user_id', $userId)->exists();
    }

    /**
     * Get the maps count attribute
     */
    public function getMapsCountAttribute()
    {
        return $this->maps()->count();
    }

    /**
     * Scope to get public maplists only
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to get maplists by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get most liked maplists
     */
    public function scopeMostLiked($query, $limit = 10)
    {
        return $query->where('is_public', true)
            ->orderBy('likes_count', 'desc')
            ->limit($limit);
    }

    /**
     * Scope to get most favorited maplists
     */
    public function scopeMostFavorited($query, $limit = 10)
    {
        return $query->where('is_public', true)
            ->orderBy('favorites_count', 'desc')
            ->limit($limit);
    }

    /**
     * Tags associated with this maplist
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'maplist_tag')
            ->withPivot('user_id')
            ->withTimestamps();
    }
}
