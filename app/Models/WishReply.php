<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in the thread under a wish. See the migration for why this is
 * not a comment system.
 */
class WishReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'wish_id',
        'user_id',
        'by_admin',
        'body',
    ];

    protected $casts = [
        'by_admin' => 'boolean',
    ];

    public const MAX_LENGTH = 2000;

    public function wish(): BelongsTo
    {
        return $this->belongsTo(Wish::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
