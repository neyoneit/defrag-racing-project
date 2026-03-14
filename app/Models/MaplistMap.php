<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaplistMap extends Model
{
    use HasFactory;

    protected $fillable = [
        'maplist_id',
        'map_id',
        'position',
    ];

    /**
     * Get the maplist
     */
    public function maplist()
    {
        return $this->belongsTo(Maplist::class);
    }

    /**
     * Get the map
     */
    public function map()
    {
        return $this->belongsTo(Map::class);
    }
}
