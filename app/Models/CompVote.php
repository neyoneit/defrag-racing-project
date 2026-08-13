<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One person's pick on one ballot. Everyone gets a vote in each physics.
 *
 * `created_at` is not decoration: two maps level on combined votes are split
 * by which reached that count first, which means the time each vote landed.
 */
class CompVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'comp_round_id',
        'comp_candidate_id',
        'user_id',
        'physics',
    ];

    public function round()
    {
        return $this->belongsTo(CompRound::class, 'comp_round_id');
    }

    public function candidate()
    {
        return $this->belongsTo(CompCandidate::class, 'comp_candidate_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
