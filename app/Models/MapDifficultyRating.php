<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapDifficultyRating extends Model
{
    protected $fillable = [
        'map_id',
        'user_id',
        'rating',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
