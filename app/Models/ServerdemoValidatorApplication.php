<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServerdemoValidatorApplication extends Model
{
    /**
     * Deleting hides an application, it does not destroy it. Rejecting is the
     * decision and stays on the record; deleting only clears the row out of
     * the way so the person may apply again. Both facts survive - see the
     * migration that added this.
     */
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'motivation',
        'experience',
        'availability',
        'contact',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * An application still in play - the applicant should not be able to send
     * a second one while the first is being considered or has been accepted.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'shortlisted', 'approved']);
    }
}
