<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoAssignmentReport extends Model
{
    protected $fillable = [
        'demo_id',
        'report_type',
        'reported_by_user_id',
        'current_record_id',
        'suggested_record_id',
        'reason_type',
        'reason_details',
        'status',
        'resolved_by_admin_id',
        'resolved_at',
        'admin_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function demo()
    {
        return $this->belongsTo(UploadedDemo::class, 'demo_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function currentRecord()
    {
        return $this->belongsTo(Record::class, 'current_record_id');
    }

    public function suggestedRecord()
    {
        return $this->belongsTo(Record::class, 'suggested_record_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }
}
