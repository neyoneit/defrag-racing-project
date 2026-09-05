<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoutubePlaylistItem extends Model
{
    protected $fillable = [
        'youtube_playlist_id',
        'rendered_video_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function playlist()
    {
        return $this->belongsTo(YoutubePlaylist::class, 'youtube_playlist_id');
    }

    public function video()
    {
        return $this->belongsTo(RenderedVideo::class, 'rendered_video_id');
    }
}
