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

    /**
     * The badge travels with the listing. Without this every page that shows
     * a listing would need its own copy of the label table, which is exactly
     * what this replaced.
     */
    protected $appends = [
        'work_type_label',
        'work_type_color',
        'work_type_pending',
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

    public function workType()
    {
        return $this->belongsTo(MarketplaceWorkType::class, 'work_type', 'slug');
    }

    public function getWorkTypeLabelAttribute(): string
    {
        $type = MarketplaceWorkType::find_cached($this->work_type);

        return $type ? $type->localized('label') : (string) $this->work_type;
    }

    public function getWorkTypeColorAttribute(): string
    {
        return MarketplaceWorkType::find_cached($this->work_type)?->color ?? 'gray';
    }

    /** True while an admin has not confirmed a type somebody suggested. */
    public function getWorkTypePendingAttribute(): bool
    {
        return MarketplaceWorkType::find_cached($this->work_type)?->status === 'pending';
    }

    /** Slugs that may be picked right now. */
    public static function workTypes(): array
    {
        return array_column(MarketplaceWorkType::options(), 'value');
    }

    /** English label, for the admin and anything that is not a page. */
    public static function workTypeLabel(string $type): string
    {
        return MarketplaceWorkType::find_cached($type)?->label ?? $type;
    }
}
