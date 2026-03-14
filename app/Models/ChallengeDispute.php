<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'claimer_id',
        'creator_id',
        'reason',
        'evidence',
        'creator_response',
        'creator_responded_at',
        'status',
        'resolved_by_admin_id',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'creator_responded_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function challenge()
    {
        return $this->belongsTo(HeadhunterChallenge::class, 'challenge_id');
    }

    public function claimer()
    {
        return $this->belongsTo(User::class, 'claimer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function resolvedByAdmin()
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }

    // Helper methods
    public function isOverdue()
    {
        if ($this->creator_responded_at || $this->status !== 'pending') {
            return false;
        }

        return $this->created_at->addDays(14)->isPast();
    }

    public function daysUntilAutoBan()
    {
        if ($this->creator_responded_at || $this->status !== 'pending') {
            return null;
        }

        $deadline = $this->created_at->addDays(14);
        return now()->diffInDays($deadline, false);
    }
}
