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
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'flagged_by_users' => 'array',
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
}
