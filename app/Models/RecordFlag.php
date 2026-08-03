<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordFlag extends Model
{
    protected $fillable = [
        'record_id',
        'demo_id',
        'flag_type',
        'flagged_by_user_id',
        'flagged_by_users',
        'flag_count',
        'status',
        'note',
        'resolved_by_admin_id',
        'resolved_at',
        'admin_notes',
        'admin_cleared_at',
        'admin_cleared_by',
        'validation_case_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'flagged_by_users' => 'array',
        'admin_cleared_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(Record::class);
    }

    public function demo()
    {
        return $this->belongsTo(UploadedDemo::class, 'demo_id');
    }

    public function flagger()
    {
        return $this->belongsTo(User::class, 'flagged_by_user_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }

    public function clearedBy()
    {
        return $this->belongsTo(User::class, 'admin_cleared_by');
    }

    /**
     * The case this report was folded into - everything reported against the
     * same player lives together, see ServerdemoValidationCase.
     */
    public function validationCase()
    {
        return $this->belongsTo(ServerdemoValidationCase::class, 'validation_case_id');
    }

    /**
     * Enough different people reported this independently.
     *
     * `flag_count` is the accumulated total; one person reporting five times
     * does not move it, which is what makes this worth checking at all.
     */
    public function hasEnoughReports(): bool
    {
        return $this->flag_count >= config('serverdemos.validation.min_reports', 2);
    }

    /** Cleared by the admin AND reported by enough people. Both, always. */
    public function isReadyForValidators(): bool
    {
        return $this->admin_cleared_at !== null && $this->hasEnoughReports();
    }

    /**
     * The serverdemo of the run this flag is about, or null when none was
     * ever recorded. A flag with no serverdemo has nothing for a validator to
     * look at, so it stays with the admin.
     */
    public function serverDemo(): ?ServerDemo
    {
        return $this->record ? ServerDemo::forRecord($this->record)->first() : null;
    }

    public function scopeAwaitingClearance($query)
    {
        return $query->whereNull('admin_cleared_at')->where('status', 'pending');
    }

    /** Cleared reports that were folded into a case. */
    public function scopeInValidation($query)
    {
        return $query->whereNotNull('admin_cleared_at')->whereNotNull('validation_case_id');
    }
}
