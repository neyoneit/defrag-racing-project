<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityTaskMapSkip extends Model
{
    protected $table = 'community_task_map_skips';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'map_id',
        'kind',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function map()
    {
        return $this->belongsTo(Map::class);
    }
}
