<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityTaskSession extends Model
{
    protected $fillable = [
        'user_id',
        'points',
        'assignments_completed',
        'ratings_completed',
    ];

    protected $casts = [
        'points' => 'integer',
        'assignments_completed' => 'integer',
        'ratings_completed' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
