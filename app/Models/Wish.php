<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wish extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'project',
        'title',
        'body',
        'status',
        'status_note',
        'approved_at',
        'approved_by',
        'removal_requested_at',
        'removal_reason',
        'upvotes',
        'downvotes',
        'score',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'removal_requested_at' => 'datetime',
    ];

    public const STATUSES = [
        'considering' => 'Considering',
        'planned' => 'Planned',
        'done' => 'Done',
        'rejected' => 'Not happening',
    ];

    /**
     * Everything a wish can be about: the public repositories in the org, plus
     * the two things that are ours but are not repositories, plus a catch-all.
     * Without the catch-all people file website wishes about the engine.
     */
    public const PROJECTS = [
        'web' => 'defrag.racing',
        'launcher' => 'Defrag Launcher',
        'defraglive' => 'DefragLive',
        'defraglive_extension' => 'DefragLive extension',
        'demome' => 'Demome',
        'defraglegends' => 'DefragLegends',
        'odfe' => 'oDFe engine',
        'server_bundle' => 'Server bundle Linux',
        'server_bundle_windows' => 'Server bundle (Windows)',
        'other' => 'Something else',
    ];

    /**
     * How many upvotes one person gets to spend, in total, across the board.
     *
     * It grows with the board so the rule never becomes absurd: five while the
     * list is short, a third of it once it is long. A fixed number ages badly -
     * five votes over two hundred wishes is not a priority, it is a lottery
     * ticket.
     *
     * Downvotes are deliberately outside the budget. The budget exists to make
     * people choose what they want most; saying "this is a bad idea" is not a
     * want and rationing it would just silence the objections.
     */
    public static function voteBudget(): int
    {
        return max(5, (int) ceil(static::approved()->count() / 3));
    }

    /**
     * Upvotes this person has already spent. Your own wish does not count:
     * the upvote on it is placed automatically when you post, so charging for
     * it would mean writing wishes costs you the right to support other
     * people's.
     */
    public static function upvotesSpentBy(int $userId): int
    {
        return WishVote::query()
            ->join('wishes', 'wishes.id', '=', 'wish_votes.wish_id')
            ->where('wish_votes.user_id', $userId)
            ->where('wish_votes.value', 1)
            ->whereNull('wishes.deleted_at')
            ->whereNotNull('wishes.approved_at')
            ->where('wishes.user_id', '!=', $userId)
            ->count();
    }

    /** Votes left, never negative - the budget can shrink when wishes go. */
    public static function upvotesLeftFor(int $userId): int
    {
        return max(0, static::voteBudget() - static::upvotesSpentBy($userId));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function votes()
    {
        return $this->hasMany(WishVote::class);
    }

    /** Live on the public list. */
    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Recount from the votes and store the result.
     *
     * Counted rather than incremented on purpose: a vote can be added,
     * flipped or taken back, and three separate delta paths is three chances
     * for the cached number to drift away from the rows that justify it.
     */
    public function recount(): void
    {
        $this->upvotes = $this->votes()->where('value', 1)->count();
        $this->downvotes = $this->votes()->where('value', -1)->count();
        $this->score = $this->upvotes - $this->downvotes;
        $this->save();
    }
}
