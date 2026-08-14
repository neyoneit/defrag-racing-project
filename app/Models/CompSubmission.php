<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A run entered into a round.
 *
 * Several rows per person is normal and intended: people improve through the
 * round, upload again, and only their best valid time is scored. There is
 * deliberately no unique key pinning somebody to one attempt - an entry is a
 * standing, improvable thing, not a single shot.
 *
 * Nothing arrives here by itself. Serverdemos are collected from bundle
 * servers automatically and are not read by comps at all; an entry exists only
 * because somebody uploaded it here. When an online demo happens to line up
 * with a scraped record, `matched_record_id` records that, which is a nicety
 * for the site and changes nothing about the entry.
 */
class CompSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'comp_round_id',
        'user_id',
        'mdd_id',
        'physics',
        'time',
        'uploaded_demo_id',
        'is_online',
        'is_highlight',
        'status',
        'invalid_reason',
        'removed_by',
        'removed_at',
        'matched_record_id',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'is_highlight' => 'boolean',
        'removed_at' => 'datetime',
    ];

    public function round()
    {
        return $this->belongsTo(CompRound::class, 'comp_round_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function demo()
    {
        return $this->belongsTo(UploadedDemo::class, 'uploaded_demo_id');
    }

    public function reports()
    {
        return $this->hasMany(CompDemoReport::class);
    }

    public function removedBy()
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'valid');
    }

    /**
     * Runs an admin took out, as opposed to ones the validator never accepted.
     * Both are `invalid`; only these were ever a real entry, and only these
     * stay visible on the round page - see the removed_by migration.
     */
    public function scopeRemovedByAdmin($query)
    {
        return $query->where('status', 'invalid')->whereNotNull('removed_by');
    }

    public function wasRemovedByAdmin(): bool
    {
        return $this->status === 'invalid' && $this->removed_by !== null;
    }

    /**
     * What the leaderboard is built from: valid, and entered as an entry
     * rather than uploaded as a curiosity.
     */
    public function scopeCounting($query)
    {
        return $query->where('status', 'valid')->where('is_highlight', false);
    }
}
