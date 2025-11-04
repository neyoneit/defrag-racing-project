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
        'country',
        'record_date',
        'status',
        'processing_output',
        'name_confidence',
        'suggested_user_id',
        'matched_alias',
        'manually_assigned',
        'download_count',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'time_ms' => 'integer',
        'record_date' => 'datetime',
        'name_confidence' => 'integer',
        'manually_assigned' => 'boolean',
        'download_count' => 'integer',
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
     * Assignment reports for this demo
     */
    public function assignmentReports()
    {
        return $this->hasMany(DemoAssignmentReport::class, 'demo_id');
    }

    /**
     * Suggested user based on name matching
     */
    public function suggestedUser()
    {
        return $this->belongsTo(User::class, 'suggested_user_id');
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

    /**
     * Increment download counter
     */
    public function incrementDownloads()
    {
        $this->increment('download_count');
    }
}
