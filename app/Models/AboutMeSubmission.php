<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutMeSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'submitted_by',
        'content',
        'type',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
