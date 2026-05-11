<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedMapFilter extends Model
{
    protected $fillable = ['user_id', 'name', 'filter_state'];

    protected $casts = [
        'filter_state' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
