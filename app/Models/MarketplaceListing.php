<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceListing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'listing_type',
        'work_type',
        'title',
        'description',
        'budget',
        'status',
        'assigned_to_user_id',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function reviews()
    {
        return $this->hasMany(MarketplaceReview::class, 'listing_id');
    }

    public function scopeRequests($query)
    {
        return $query->where('listing_type', 'request');
    }

    public function scopeOffers($query)
    {
        return $query->where('listing_type', 'offer');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public static function workTypes(): array
    {
        return ['map', 'player_model', 'weapon_model', 'shadow_model'];
    }

    public static function workTypeLabel(string $type): string
    {
        return match ($type) {
            'map' => 'Map',
            'player_model' => 'Player Model',
            'weapon_model' => 'Weapon Model',
            'shadow_model' => 'Shadow Model',
            default => $type,
        };
    }
}
