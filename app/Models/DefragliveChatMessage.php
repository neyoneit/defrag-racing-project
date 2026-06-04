<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DefragliveChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'defraglive_chat_messages';

    protected $fillable = [
        'message_id',
        'action',
        'author',
        'content',
        'msg_timestamp',
        'payload',
        'resolved_user_id',
        'resolved_mdd_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'msg_timestamp' => 'double',
    ];

    public function resolvedUser()
    {
        return $this->belongsTo(User::class, 'resolved_user_id');
    }
}
