<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapperClaimExclusion extends Model
{
    protected $fillable = [
        'mapper_claim_id',
        'map_id',
    ];

    public function claim()
    {
        return $this->belongsTo(MapperClaim::class, 'mapper_claim_id');
    }

    public function map()
    {
        return $this->belongsTo(Map::class);
    }
}
