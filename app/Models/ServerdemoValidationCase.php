<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Everything reported against one player, in one place.
 *
 * The ladder lives here rather than on the individual report: a validator
 * takes on a player, not a run, and hands on the whole picture when they are
 * unsure. See the migration for why the grouping is by MDD id.
 */
class ServerdemoValidationCase extends Model
{
    protected $table = 'serverdemo_validation_cases';

    protected $fillable = [
        'subject_mdd_id',
        'subject_user_id',
        'subject_name',
        'kind',
        'validation_stage',
        'assigned_to_user_id',
        'assigned_at',
        'validators_seen',
        'validation_outcome',
        'validation_closed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'validators_seen' => 'array',
        'validation_closed_at' => 'datetime',
    ];

    public const KIND_SERVERDEMO = 'serverdemo';
    public const KIND_PUBLIC_DEMO = 'public_demo';

    public function flags()
    {
        return $this->hasMany(RecordFlag::class, 'validation_case_id');
    }

    public function comments()
    {
        return $this->hasMany(ServerdemoValidationComment::class, 'validation_case_id')->orderBy('created_at');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function subjectUser()
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function isOpen(): bool
    {
        return $this->validation_closed_at === null;
    }

    /** What to call this player on screen, best identifier first. */
    public function subjectLabel(): string
    {
        if ($this->subject_name) {
            return $this->subject_name;
        }

        if ($this->subject_mdd_id) {
            return 'MDD #' . $this->subject_mdd_id;
        }

        return $this->subjectUser?->name ?? 'unknown player';
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('validation_closed_at');
    }

    /**
     * Everyone who must never be handed this case: the people who reported it
     * and anyone who ever shared a clan with them.
     *
     * Without this, being a validator is a way to order up demos. Report a
     * pile of runs, wait for the random assignment, and a share of them comes
     * back to you - which is exactly the objection raised when the validator
     * page went up, and it was a fair one.
     *
     * Clan membership is taken with the soft-deleted rows included on both
     * hops, so it is the whole history rather than the current roster: a clan
     * the reporter has since left still blocks its members, and a member who
     * has since left that clan is still blocked. Leaving a clan the day before
     * reporting therefore buys nothing.
     */
    public function conflictedUserIds(): array
    {
        $reporters = $this->flags()
            ->get(['id', 'validation_case_id', 'flagged_by_user_id', 'flagged_by_users'])
            ->flatMap(fn (RecordFlag $flag) => array_merge(
                $flag->flagged_by_users ?? [],
                [$flag->flagged_by_user_id]
            ))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($reporters->isEmpty()) {
            return [];
        }

        return $reporters->merge(self::clanmateIds($reporters->all()))->unique()->values()->all();
    }

    /**
     * The mirror of conflictedUserIds(), asked from the other side: which
     * reporters would disqualify THIS user. Same rule, phrased so the case
     * list can filter in the database instead of loading every case.
     */
    public static function conflictIdsFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return collect([$user->id])
            ->merge(self::clanmateIds([$user->id]))
            ->unique()
            ->values()
            ->all();
    }

    /** Everyone who has ever been in a clan these users have ever been in. */
    private static function clanmateIds(array $userIds): array
    {
        $clanIds = ClanPlayer::withTrashed()
            ->whereIn('user_id', $userIds)
            ->pluck('clan_id')
            ->unique();

        if ($clanIds->isEmpty()) {
            return [];
        }

        return ClanPlayer::withTrashed()
            ->whereIn('clan_id', $clanIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Cases none of these users reported. `flagged_by_users` holds every
     * reporter of a report, `flagged_by_user_id` only the first, so both have
     * to be checked or the person who opened the report slips through.
     */
    public function scopeNotReportedBy($query, array $userIds)
    {
        if ($userIds === []) {
            return $query;
        }

        return $query->whereDoesntHave('flags', function ($flags) use ($userIds) {
            $flags->whereIn('flagged_by_user_id', $userIds);

            foreach ($userIds as $id) {
                $flags->orWhereJsonContains('flagged_by_users', (int) $id);
            }
        });
    }
}
