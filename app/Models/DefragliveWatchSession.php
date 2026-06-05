<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One continuous stretch the DefragLive bot spent spectating a single player.
 * Written/extended by DefragliveWatchService::accrue() from the ingest
 * serverstate branch. The contest leaderboard sums `seconds` per resolved
 * player (mdd_id, else player_name_clean) over the contest window.
 */
class DefragliveWatchSession extends Model
{
    protected $table = 'defraglive_watch_sessions';

    protected $fillable = [
        'mdd_id',
        'user_id',
        'player_name',
        'player_name_clean',
        'ip',
        'mapname',
        'seconds',
        'started_at',
        'last_seen_at',
        'ended_at',
    ];

    protected $casts = [
        'seconds' => 'integer',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The single currently-open session (the bot watches one player at a time). */
    public function scopeOpen($query)
    {
        return $query->whereNull('ended_at');
    }
}
