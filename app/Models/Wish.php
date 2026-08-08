<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Wish extends Model
{
    use SoftDeletes;

    /**
     * Screenshots go on the local public disk, not the community bucket: they
     * are small, they are part of the page rather than something anyone
     * downloads, and storage/app/public is already in the nightly mirror.
     */
    public const IMAGE_DISK = 'public';

    public const IMAGE_DIR = 'wishes';

    protected $fillable = [
        'user_id',
        'project',
        'title',
        'body',
        'image_path',
        'youtube_id',
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
     * Three rules stacked, in this order:
     *
     * - never more votes than there are wishes. One wish on the board is one
     *   vote, not five - a budget that offers more than there is to spend is
     *   not a budget, and it makes the counter read as broken.
     * - five while the list is short, so an early board is not a fight over
     *   two votes.
     * - a third of the board once it is long, because a fixed number ages
     *   badly: five votes over two hundred wishes is a lottery ticket, not a
     *   priority.
     *
     * Downvotes are deliberately outside the budget. The budget exists to make
     * people choose what they want most; saying "this is a bad idea" is not a
     * want and rationing it would just silence the objections.
     */
    public static function voteBudget(): int
    {
        $wishes = static::approved()->count();

        return min($wishes, max(5, (int) ceil($wishes / 3)));
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

    /** Where the browser fetches the screenshot, or null when there is none. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(self::IMAGE_DISK)->url($this->image_path) : null;
    }

    /**
     * The eleven-character id out of whatever form of YouTube link someone
     * pasted - watch, youtu.be, embed, shorts, or the bare id itself. Null
     * when it is not a YouTube link at all, which is what the validator
     * reports back to the author.
     */
    public static function youtubeId(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
            return $url;
        }

        return preg_match(
            '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~',
            $url,
            $m
        ) ? $m[1] : null;
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
