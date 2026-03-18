<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceCreatorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_listed',
        'accepting_commissions',
        'specialties',
        'bio',
        'rate_maps',
        'rate_models',
        'featured_map_ids',
        'portfolio_urls',
    ];

    protected $casts = [
        'is_listed' => 'boolean',
        'accepting_commissions' => 'boolean',
        'specialties' => 'array',
        'featured_map_ids' => 'array',
        'portfolio_urls' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function featuredMaps()
    {
        if (empty($this->featured_map_ids)) {
            return Map::whereRaw('1 = 0');
        }

        return Map::whereIn('id', $this->featured_map_ids);
    }
}
