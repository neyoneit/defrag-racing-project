<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerOwnerApplication extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'server_info',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        // server_info stores a structured list of the applicant's defrag
        // servers — see ServerHostingController::apply() for the shape.
        'server_info' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function credential()
    {
        return $this->hasOne(SftpCredential::class, 'application_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
