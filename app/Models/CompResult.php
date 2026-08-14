<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A frozen row of a finished round's standings.
 *
 * Equal times share a rank rather than being separated by who uploaded first.
 * On a strafe map an exact tie is common enough that breaking it on upload
 * time would decide a competition on reflexes about clicking, so two people
 * first are both first, and both count as a win towards a wildcard.
 *
 * `points` is only filled for season. Weekly has one round and the fastest
 * time wins it, so there is nothing to add up.
 */
class CompResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'comp_round_id',
        'physics',
        'user_id',
        'rank',
        'time',
        'points',
    ];

    protected $casts = [
        'points' => 'decimal:1',
    ];

    public function round()
    {
        return $this->belongsTo(CompRound::class, 'comp_round_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWinners($query)
    {
        return $query->where('rank', 1);
    }
}
