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
}
