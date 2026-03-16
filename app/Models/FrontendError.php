<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrontendError extends Model
{
    protected $fillable = [
        'user_id', 'type', 'message', 'stack', 'url', 'endpoint',
        'status_code', 'request_data', 'response_data', 'component',
        'user_agent', 'ip',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
