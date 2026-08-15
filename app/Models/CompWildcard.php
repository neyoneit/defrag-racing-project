<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The right to name a round's map outright, skipping the vote.
 *
 * First one spent on a round wins it. Other holders keep theirs and wait for
 * another round - nothing is taken from them, and no votes are thrown away
 * either. The votes stay on record, they just stop deciding anything.
 *
 * Winners come in pairs, one per physics, so a right is EARNED in one of them -
 * that is `physics` - but it may be spent on either ballot, and `used_physics`
 * records which one it actually decided. Tying it to its own physics punished
 * people for being good at one thing: it made a right you had won useless on
 * the ballot you cared about.
 */
class CompWildcard extends Model
{
    use HasFactory;

    public const FROM_SEASON = 'season_win';
    public const FROM_WEEKLIES = 'five_weekly_wins';

    /** How many weekly wins earn one. They need not be consecutive. */
    public const WEEKLY_WINS_REQUIRED = 3;

    protected $fillable = [
        'user_id',
        'physics',
        'source',
        'source_comp_id',
        'used_at',
        'used_physics',
        'used_on_round_id',
        'used_map_id',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sourceComp()
    {
        return $this->belongsTo(Comp::class, 'source_comp_id');
    }

    public function usedOnRound()
    {
        return $this->belongsTo(CompRound::class, 'used_on_round_id');
    }

    public function usedMap()
    {
        return $this->belongsTo(Map::class, 'used_map_id');
    }

    public function scopeUnused($query)
    {
        return $query->whereNull('used_at');
    }

    public function isSpent(): bool
    {
        return $this->used_at !== null;
    }
}
