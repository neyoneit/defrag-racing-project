<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityTaskVote extends Model
{
    protected $fillable = [
        'user_id',
        'demo_id',
        'vote_type',
        'task_type',
        'selected_record_id',
        'consensus_status',
        'resolved_by',
        'resolved_at',
        'admin_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function demo()
    {
        return $this->belongsTo(UploadedDemo::class, 'demo_id');
    }

    public function selectedRecord()
    {
        return $this->belongsTo(Record::class, 'selected_record_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get all votes for a specific demo, grouped for consensus check.
     */
    public static function getVoteSummary(int $demoId): array
    {
        $votes = static::where('demo_id', $demoId)->get();

        return [
            'total' => $votes->count(),
            'assign' => $votes->where('vote_type', 'assign')->count(),
            'not_sure' => $votes->where('vote_type', 'not_sure')->count(),
            'no_match' => $votes->where('vote_type', 'no_match')->count(),
            'correct' => $votes->where('vote_type', 'correct')->count(),
            'unassign' => $votes->where('vote_type', 'unassign')->count(),
            'better_match' => $votes->where('vote_type', 'better_match')->count(),
        ];
    }
}
