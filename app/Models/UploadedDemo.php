<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadedDemo extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'processed_filename',
        'file_path',
        'file_size',
        'file_hash',
        'user_id',
        'record_id',
        'map_name',
        'physics',
        'gametype',
        'time_ms',
        'player_name',
        'record_date',
        'status',
        'processing_output',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'time_ms' => 'integer',
        'record_date' => 'datetime',
    ];

    /**
     * User who uploaded the demo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associated online record
     */
    public function record()
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * Associated offline record
     */
    public function offlineRecord()
    {
        return $this->hasOne(OfflineRecord::class, 'demo_id');
    }

    /**
     * Get the full storage path
     */
    public function getFullPathAttribute()
    {
        return storage_path('app/' . $this->file_path);
    }

    /**
     * Get formatted time
     */
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

    /**
     * Check if demo is from online/multiplayer run
     */
    public function getIsOnlineAttribute()
    {
        // Online gametypes start with 'm': mdf, mfs, mfc
        return $this->gametype && str_starts_with($this->gametype, 'm');
    }

    /**
     * Check if demo is from offline run
     */
    public function getIsOfflineAttribute()
    {
        // Offline gametypes: df, fs, fc
        return $this->gametype && !str_starts_with($this->gametype, 'm');
    }
}
