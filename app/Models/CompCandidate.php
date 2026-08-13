<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A map on the ballot.
 *
 * `blocked_physics` names the physics the map CANNOT be voted in, because it
 * cannot be finished in it. A map reported and confirmed as cpmonly blocks
 * 'vq3'. Null - the ordinary case - means it appears on both ballots.
 */
class CompCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'comp_round_id',
        'map_id',
        'votes_cpm',
        'votes_vq3',
        'blocked_physics',
    ];

    public function round()
    {
        return $this->belongsTo(CompRound::class, 'comp_round_id');
    }

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function votes()
    {
        return $this->hasMany(CompVote::class);
    }

    public function votableIn(string $physics): bool
    {
        return $this->blocked_physics !== $physics;
    }

    public function votesIn(string $physics): int
    {
        return $physics === 'cpm' ? $this->votes_cpm : $this->votes_vq3;
    }

    /**
     * Both ballots added together. This is the first tiebreak: when two maps
     * finish level on one ballot, the one the whole site preferred wins, even
     * if it lost the other physics.
     */
    public function votesCombined(): int
    {
        return $this->votes_cpm + $this->votes_vq3;
    }
}
