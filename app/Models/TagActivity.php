<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TagActivity extends Model
{
    public $timestamps = false;

    protected $table = 'tag_activity_log';

    protected $fillable = [
        'user_id',
        'tag_id',
        'action',
        'taggable_type',
        'taggable_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function log(string $action, int $userId, int $tagId, string $taggableType, int $taggableId, ?array $metadata = null): static
    {
        return static::create([
            'user_id' => $userId,
            'tag_id' => $tagId,
            'action' => $action,
            'taggable_type' => $taggableType,
            'taggable_id' => $taggableId,
            'metadata' => $metadata,
        ]);
    }
}
