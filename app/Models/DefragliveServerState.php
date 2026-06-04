<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefragliveServerState extends Model
{
    protected $table = 'defraglive_server_state';

    protected $fillable = [
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
