<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A round: one map (per physics), voted on beforehand and played for a fixed
 * window. Weekly has one of these, season has five.
 *
 * The status walks one way and never back:
 *
 *   voting  - the ballot is open, nobody is playing this yet
 *   locked  - voting closed, map decided, play has not started
 *   active  - being played, uploads accepted
 *   finished - closed, results frozen, demos become downloadable
 */
class CompRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'comp_id',
        'index',
        'category',
        'weapon',
        'voting_opens_at',
        'voting_closes_at',
        'starts_at',
        'ends_at',
        'status',
        'prize_eur',
    ];

    protected $casts = [
        'voting_opens_at' => 'datetime',
        'voting_closes_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function comp()
    {
        return $this->belongsTo(Comp::class);
    }

    /**
     * Ordered by id, which is the order they were drawn in.
     *
     * Without it MySQL is free to hand them back differently on each request,
     * and the ballot visibly reshuffled itself every time the page was
     * refreshed - which makes it hard to find the map you were looking at and
     * makes a vote feel like it landed somewhere else.
     *
     * Insertion order and not, say, alphabetical or by length: the draw takes
     * one map per band of record time and shuffles before writing, so this
     * order is already random with respect to length. Sorting by anything
     * meaningful would tell everybody which slot each map came out of.
     */
    public function candidates()
    {
        return $this->hasMany(CompCandidate::class)->orderBy('id');
    }

    /** The winning map, one row per physics. */
    public function maps()
    {
        return $this->hasMany(CompRoundMap::class);
    }

    public function votes()
    {
        return $this->hasMany(CompVote::class);
    }

    public function submissions()
    {
        return $this->hasMany(CompSubmission::class);
    }

    public function results()
    {
        return $this->hasMany(CompResult::class);
    }

    public function mapFor(string $physics): ?CompRoundMap
    {
        return $this->maps->firstWhere('physics', $physics);
    }

    public function isVoting(): bool
    {
        return $this->status === 'voting';
    }

    public function acceptsUploads(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Demos stay hidden while a round is being played - showing them would
     * hand everyone the route. Once it is finished they are ordinary demos.
     */
    public function demosVisible(): bool
    {
        return $this->status === 'finished';
    }
}
