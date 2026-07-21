<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN = 'hidden';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'slug',
        'description',
        'youtube_url',
        'external_url',
        'source_key',
        'is_locked',
        'status',
        'defrag_only',
        'position',
        'meta',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'defrag_only' => 'boolean',
        'downloads_count' => 'integer',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(DownloadCategory::class, 'category_id');
    }

    public function files()
    {
        return $this->hasMany(DownloadFile::class)
            ->where('kind', DownloadFile::KIND_FILE)
            ->orderBy('position');
    }

    public function screenshots()
    {
        return $this->hasMany(DownloadFile::class)
            ->where('kind', DownloadFile::KIND_SCREENSHOT)
            ->orderBy('position');
    }

    /**
     * Every attachment regardless of kind. Used when deleting an entry, where
     * the files() / screenshots() filters would leave orphans behind.
     */
    public function allFiles()
    {
        return $this->hasMany(DownloadFile::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * The YouTube video id pulled out of whatever URL form the uploader
     * pasted (watch?v=, youtu.be/, /embed/, /shorts/). Null when the URL is
     * empty or is not a YouTube link we recognise, in which case the detail
     * page simply renders no player.
     */
    public function getYoutubeIdAttribute(): ?string
    {
        if (! $this->youtube_url) {
            return null;
        }

        $patterns = [
            '~youtube\.com/watch\?(?:.*&)?v=([\w-]{11})~i',
            '~youtu\.be/([\w-]{11})~i',
            '~youtube\.com/embed/([\w-]{11})~i',
            '~youtube\.com/shorts/([\w-]{11})~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->youtube_url, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
