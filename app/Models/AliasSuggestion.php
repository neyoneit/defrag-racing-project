<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AliasSuggestion extends Model
{
    protected $fillable = [
        'user_id',
        'suggested_by_user_id',
        'alias',
        'status',
        'note',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The user who is being suggested the alias
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who made the suggestion
     */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by_user_id');
    }
}
