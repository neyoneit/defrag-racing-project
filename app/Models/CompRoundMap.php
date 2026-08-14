<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The map a round is played on, one row per physics.
 *
 * This is also the record of what has ever been played, and so the answer to
 * "may this map be drawn again?" - it may not. A map that only ever lost a
 * ballot never reaches this table and stays in the pool.
 */
class CompRoundMap extends Model
{
    use HasFactory;

    protected $fillable = [
        'comp_round_id',
        'physics',
        'map_id',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function round()
    {
        return $this->belongsTo(CompRound::class, 'comp_round_id');
    }

    public function map()
    {
        return $this->belongsTo(Map::class);
    }
}
