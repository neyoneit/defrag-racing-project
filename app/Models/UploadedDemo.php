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
        'time_ms',
        'player_name',
        'status',
        'processing_output',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'time_ms' => 'integer',
    ];

    /**
     * User who uploaded the demo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associated record
     */
    public function record()
    {
        return $this->belongsTo(Record::class);
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
}
