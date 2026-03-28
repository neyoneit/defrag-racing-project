<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerMapScore extends Model
{
    protected $fillable = [
        'mdd_id',
        'user_id',
        'mapname',
        'physics',
        'mode',
        'time',
        'reltime',
        'map_score',
        'is_outlier',
    ];

    protected $casts = [
        'reltime' => 'float',
        'map_score' => 'float',
        'is_outlier' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
