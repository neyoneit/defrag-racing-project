<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'user_id',
        'status',
        'record_id',
        'offline_record_id',
        'submission_notes',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Relationships
    public function challenge()
    {
        return $this->belongsTo(HeadhunterChallenge::class, 'challenge_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function record()
    {
        return $this->belongsTo(Record::class, 'record_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function offlineRecord()
    {
        return $this->belongsTo(OfflineRecord::class, 'offline_record_id');
    }

    // Helper methods
    public function isSubmitted()
    {
        return in_array($this->status, ['submitted', 'approved', 'rejected']);
    }

    public function canSubmit()
    {
        return in_array($this->status, ['participating', 'rejected']);
    }

    public function hasProof()
    {
        return $this->record_id !== null || $this->offline_record_id !== null;
    }
}
