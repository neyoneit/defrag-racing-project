<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A time its own owner withdrew. See the migration for why it is a snapshot.
 */
class PlayerSelfReport extends Model
{
    protected $fillable = [
        'user_id',
        'mdd_id',
        'player_name',
        'record_id',
        'mapname',
        'physics',
        'mode',
        'gametype',
        'time',
        'reason',
        'note',
        'handling',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * When the player wants it handled. `mode` was already taken by the game
     * mode of the run, so the column is `handling` - naming it `mode` twice
     * would have been a bug waiting for whoever reads this next.
     */
    public const HANDLING = [
        'immediate' => 'Hide it here as soon as an admin approves',
        'on_merge' => 'Wait for the MDD merge and do both at once',
    ];

    public function scopePending($query)
    {
        return $query->whereNull('processed_at');
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function map()
    {
        return $this->belongsTo(Map::class, 'mapname', 'name');
    }
}
