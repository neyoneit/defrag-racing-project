<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapNsfwFlag extends Model
{
    protected $fillable = ['user_id', 'map_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function map()
    {
        return $this->belongsTo(Map::class);
    }
}
