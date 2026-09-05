<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoutubePlaylist extends Model
{
    protected $fillable = [
        'key',
        'youtube_playlist_id',
        'sync_queued',
        'planned_count',
        'synced_count',
        'computed_at',
        'synced_at',
    ];

    protected $casts = [
        'sync_queued' => 'boolean',
        'planned_count' => 'integer',
        'synced_count' => 'integer',
        'computed_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(YoutubePlaylistItem::class);
    }
}
