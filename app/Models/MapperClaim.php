<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapperClaim extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get maps matching this claim (case-insensitive partial match on author field)
     */
    public function getMatchingMapsQuery()
    {
        return Map::where('visible', true)
            ->where('author', 'LIKE', '%' . $this->name . '%');
    }
}
