<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfflineRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'map_name',
        'physics',
        'gametype',
        'time_ms',
        'player_name',
        'demo_id',
        'rank',
        'date_set',
    ];

    protected $casts = [
        'time_ms' => 'integer',
        'rank' => 'integer',
        'date_set' => 'datetime',
    ];

    protected $appends = [
        'time', // Add time attribute for frontend compatibility
    ];

    /**
     * Demo relationship
     */
    public function demo()
    {
        return $this->belongsTo(UploadedDemo::class, 'demo_id');
    }

    /**
     * User relationship (through demo)
     */
    public function user()
    {
        return $this->hasOneThrough(
            User::class,
            UploadedDemo::class,
            'id',        // Foreign key on uploaded_demos table
            'id',        // Foreign key on users table
            'demo_id',   // Local key on offline_records table
            'user_id'    // Local key on uploaded_demos table
        );
    }

    /**
     * Get time attribute (alias for time_ms for frontend compatibility)
     */
    public function getTimeAttribute()
    {
        return $this->time_ms;
    }

    /**
     * Get formatted time
     */
    public function getFormattedTimeAttribute()
    {
        $minutes = floor($this->time_ms / 60000);
        $seconds = floor(($this->time_ms % 60000) / 1000);
        $milliseconds = $this->time_ms % 1000;

        return sprintf('%d:%02d.%03d', $minutes, $seconds, $milliseconds);
    }

    /**
     * Recalculate ranks for a specific map/physics/gametype combination
     */
    public static function recalculateRanks(string $mapName, string $physics, string $gametype)
    {
        $records = self::where('map_name', $mapName)
            ->where('physics', $physics)
            ->where('gametype', $gametype)
            ->orderBy('time_ms', 'asc')
            ->get();

        $rank = 1;
        foreach ($records as $record) {
            $record->update(['rank' => $rank++]);
        }
    }
}
