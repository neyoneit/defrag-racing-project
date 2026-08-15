<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Somebody wants an admin to look at a demo. Two reasons, one table.
 *
 * `entry` is the original: this run is not what it claims to be. Upheld, the
 * entry drops out of comps and the leaderboard recomputes without it. The demo
 * itself stays in the defrag.racing demo database - being wrong for a
 * competition is not a reason to erase a file.
 *
 * `help` is the other direction: my own demo went in and nothing happened to
 * it. Those point at a demo rather than at an entry, because the cases that
 * produce them - a file the parser could not read, a run held for a map still
 * being voted on - are exactly the cases where no entry exists.
 */
class CompDemoReport extends Model
{
    use HasFactory;

    /** Somebody else's entry is wrong. */
    public const ENTRY = 'entry';

    /** My own demo did not go through. */
    public const HELP = 'help';

    protected $fillable = [
        'comp_submission_id',
        'uploaded_demo_id',
        'kind',
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

    /**
     * The demo itself. Set on a `help` report, where there is no entry; also
     * worth reading on an `entry` report, whose demo is reachable either way.
     *
     * Held comps demos are hidden by a global scope, and a report about one is
     * precisely a report about a demo that is being held - so this relation
     * lifts the scope, or an admin would open the report and find nothing.
     */
    public function demo()
    {
        return $this->belongsTo(UploadedDemo::class, 'uploaded_demo_id')->withUnreleasedComps();
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
