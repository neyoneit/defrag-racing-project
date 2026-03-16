<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotVisit extends Model
{
    protected $fillable = ['date', 'ip', 'user_agent', 'path', 'method', 'hits', 'status_code'];

    protected $casts = [
        'date' => 'date',
    ];
}
