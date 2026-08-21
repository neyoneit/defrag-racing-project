<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Wish extends Model
{
    use SoftDeletes;

    /**
     * Tell the author when their wish is built.
     *
     * On the model rather than at the admin action, because two places in the
     * panel set a status - the one-field "Set status" action and the ordinary
     * edit form - and a third will be added the day somebody wants a bulk one.
     * Anywhere else, one of them forgets.
     *
     * Only `done`, and only when the status actually moves. Saving the same
     * status again is what an admin does while writing the public answer, and
     * it must not send the person a second notification each time.
     */
    protected static function booted(): void
    {
        static::updated(function (Wish $wish) {
            if ($wish->status !== 'done' || ! $wish->wasChanged('status')) {
                return;
            }

            $wish->notifyAuthorDone();
        });
    }

    /**
     * Tell the author their wish is built. Returns whether one was sent.
     *
     * A method rather than the body of the model event, because the event only
     * ever fires on a status CHANGE and there are wishes that were finished
     * while the notification was broken. Those authors are owed one and there
     * is no status left to change: flipping a wish out of Done and back to make
     * the event fire would be moving real data to trigger a side effect. This
     * can be called on exactly the wishes that need it.
     *
     * Never sends twice for the same wish. "Your wish is done" says nothing the
     * second time, and being able to run the backfill again without counting
     * which rows it already covered is the whole point of having it.
     */
    public function notifyAuthorDone(): bool
    {
        if (! $this->user_id) {
            return false;
        }

        // /wishlist/<id>, not the list with a tab already chosen. The wish can
        // move between tabs afterwards, and the redirect works the tab out at
        // the moment somebody clicks - see WishlistController::show.
        $url = route('wishlist.show', $this);

        if (Notification::where('type', 'wish_done')->where('url', $url)->exists()) {
            return false;
        }

        Notification::create([
            'user_id' => $this->user_id,
            'type' => 'wish_done',
            // The title carries the whole message: the notification list is a
            // single line per row, and "your wish is done" without saying which
            // wish is a notification you have to go and decode.
            'headline' => Str::limit($this->title, 90),
            // Both columns are NOT NULL with no default, so leaving them out is
            // an insert that fails on a strict MySQL - which is production,
            // while local is lax enough to fill in the blanks itself. Empty
            // rather than filled: the notification list has its own branch for
            // `wish_done` that reads `headline` and a translated sentence
            // around it, so anything put here would be English frozen into the
            // row and shown to nobody.
            'before' => '',
            'after' => '',
            'url' => $url,
        ]);

        return true;
    }

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
     * The statuses a wish is still up for grabs in, and the default view of the
     * list. The board is sorted by score, so a finished wish with fifteen votes
     * would otherwise sit at the top of it forever, above everything still
     * waiting to be decided - which is the one question the list exists to ask.
     */
    public const OPEN_STATUSES = ['considering', 'planned'];

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
