<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteDonation extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('donations:progress');
        });

        static::deleted(function () {
            Cache::forget('donations:progress');
        });
    }

    protected $fillable = [
        'user_id',
        'donor_name',
        'donor_email',
        'amount',
        'currency',
        'donation_date',
        'note',
        'status',
        'comps_amount',
        'comps_weeks',
        'comps_start_comp',
    ];

    protected $casts = [
        'donation_date' => 'date',
        'amount' => 'decimal:2',
        'comps_amount' => 'decimal:2',
        'comps_weeks' => 'integer',
        'comps_start_comp' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope for approved donations only
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Scope for pending donations
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Approved donations that actually fund comps weeks.
     *
     * All three of the comps columns have to be set for the row to mean
     * anything: money with no span of weeks does not say what it pays for, and
     * a span with no money pays nothing. A half-filled row is an admin
     * mid-thought, not a promise to anybody.
     */
    public function scopeFundsComps($query)
    {
        return $query->approved()
            ->where('comps_amount', '>', 0)
            ->where('comps_weeks', '>', 0)
            ->whereNotNull('comps_start_comp');
    }

    /**
     * What this donation adds to one physics' prize for one weekly, in euro.
     *
     * Both physics are paid, so the money buys half as many weeks as its face
     * value suggests - dividing by two here rather than at every call site is
     * what keeps that from being forgotten somewhere.
     */
    public function compsPerPhysics(): float
    {
        if (! $this->comps_weeks || $this->comps_amount <= 0) {
            return 0.0;
        }

        return round((float) $this->comps_amount / $this->comps_weeks / 2, 2);
    }

    /** The last weekly number this donation still pays for. */
    public function compsEndComp(): ?int
    {
        if (! $this->comps_weeks || $this->comps_start_comp === null) {
            return null;
        }

        return $this->comps_start_comp + $this->comps_weeks - 1;
    }

    /** True while this donation is paying for the given weekly. */
    public function compsCovers(int $compNumber): bool
    {
        $end = $this->compsEndComp();

        return $end !== null
            && $compNumber >= $this->comps_start_comp
            && $compNumber <= $end;
    }
}
