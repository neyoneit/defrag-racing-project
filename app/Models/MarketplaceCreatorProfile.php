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

    /**
     * A profile worth showing somebody.
     *
     * A profile is created for every account that links a q3df login, so
     * `is_listed` alone put 45 blank cards in the directory claiming that 45
     * people took commissions - none of whom had said so. `is_listed` stays
     * the person's own choice to be hidden; this is the other half, and
     * filling anything in is what says "yes, list me".
     */
    public function scopeWithSomethingToShow($query)
    {
        return $query->where(function ($q) {
            $q->whereRaw('JSON_LENGTH(specialties) > 0')
                ->orWhereRaw('JSON_LENGTH(featured_map_ids) > 0')
                ->orWhereRaw('JSON_LENGTH(portfolio_urls) > 0')
                ->orWhereRaw("COALESCE(bio, '') <> ''")
                ->orWhereRaw("COALESCE(rate_maps, '') <> ''")
                ->orWhereRaw("COALESCE(rate_models, '') <> ''");
        });
    }

    /** The same question about this one record, for a page that has it. */
    public function hasSomethingToShow(): bool
    {
        return ! empty($this->specialties)
            || ! empty($this->featured_map_ids)
            || ! empty($this->portfolio_urls)
            || trim((string) $this->bio) !== ''
            || trim((string) $this->rate_maps) !== ''
            || trim((string) $this->rate_models) !== '';
    }

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
