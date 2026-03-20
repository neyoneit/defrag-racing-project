<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RenderedVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'map_name',
        'player_name',
        'physics',
        'time_ms',
        'gametype',
        'record_id',
        'demo_id',
        'user_id',
        'source',
        'requested_by',
        'status',
        'priority',
        'failure_reason',
        'retry_count',
        'demo_url',
        'demo_filename',
        'youtube_url',
        'youtube_video_id',
        'render_duration_seconds',
        'video_file_size',
        'is_visible',
        'published_at',
    ];

    protected $casts = [
        'time_ms' => 'integer',
        'priority' => 'integer',
        'retry_count' => 'integer',
        'render_duration_seconds' => 'integer',
        'video_file_size' => 'integer',
        'is_visible' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        $clearCache = function ($video) {
            Cache::forget('youtube:stats');
            if ($video->user_id) {
                Cache::forget("profile:render_stats:{$video->user_id}");
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }

    public function record()
    {
        return $this->belongsTo(Record::class);
    }

    public function demo()
    {
        return $this->belongsTo(UploadedDemo::class, 'demo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getFormattedTimeAttribute()
    {
        if (!$this->time_ms) {
            return null;
        }

        $minutes = floor($this->time_ms / 60000);
        $seconds = floor(($this->time_ms % 60000) / 1000);
        $milliseconds = $this->time_ms % 1000;

        return sprintf('%d:%02d.%03d', $minutes, $seconds, $milliseconds);
    }

    public function getYoutubeThumbnailUrlAttribute()
    {
        return $this->youtube_video_id
            ? "https://img.youtube.com/vi/{$this->youtube_video_id}/mqdefault.jpg"
            : null;
    }
}
