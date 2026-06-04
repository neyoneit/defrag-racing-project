<?php

namespace App\Models;

use App\Events\DefragliveStreamEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DefragliveChatMessage extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        // Admin moderation (soft delete) -> tell every overlay to remove the
        // message instantly, in the exact ext_command/delete_message shape the
        // extension's onConsoleMessage already honours. Force deletes (cleanup)
        // don't broadcast. Dormant until Reverb is the active broadcaster.
        static::deleted(function (DefragliveChatMessage $message) {
            if ($message->isForceDeleting() || !$message->message_id) {
                return;
            }

            DefragliveStreamEvent::dispatchLive([
                'action' => 'ext_command',
                'message' => [
                    'content' => [
                        'action' => 'delete_message',
                        'id' => $message->message_id,
                    ],
                ],
            ]);
        });
    }

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
