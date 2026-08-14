<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Somebody thinks an entry is not what it claims to be.
 *
 * Upheld, the entry drops out of comps and the leaderboard recomputes without
 * it. The demo itself stays in the defrag.racing demo database - being wrong
 * for a competition is not a reason to erase a file.
 */
class CompDemoReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'comp_submission_id',
        'reported_by',
        'reason',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(CompSubmission::class, 'comp_submission_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
