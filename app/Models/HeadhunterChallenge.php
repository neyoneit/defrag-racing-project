<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HeadhunterChallenge extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'creator_id',
        'title',
        'description',
        'mapname',
        'physics',
        'mode',
        'target_time',
        'reward_amount',
        'reward_currency',
        'reward_description',
        'status',
        'claimed_by_user_id',
        'claimed_at',
        'completed_at',
        'expires_at',
        'creator_banned',
    ];

    protected $casts = [
        'target_time' => 'integer',
        'reward_amount' => 'decimal:2',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'creator_banned' => 'boolean',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function claimer()
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function disputes()
    {
        return $this->hasMany(ChallengeDispute::class, 'challenge_id');
    }

    public function participants()
    {
        return $this->hasMany(ChallengeParticipant::class, 'challenge_id');
    }

    public function approvedParticipants()
    {
        return $this->hasMany(ChallengeParticipant::class, 'challenge_id')
            ->where('status', 'approved');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'claimed']);
    }

    // Helper methods
    public function isOpen()
    {
        return $this->status === 'open' &&
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function canBeClaimed()
    {
        return $this->isOpen() && !$this->creator_banned;
    }

    public function getFormattedTimeAttribute()
    {
        $milliseconds = $this->target_time;
        $seconds = floor($milliseconds / 1000);
        $ms = $milliseconds % 1000;
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        if ($minutes > 0) {
            return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
        }
        return sprintf('%d.%03d', $seconds, $ms);
    }
}
