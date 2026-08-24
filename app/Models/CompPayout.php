<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One winner's prize for one physics of one finished round, and what became
 * of it.
 *
 * A prize is not always money leaving the account. Most weeks somebody takes
 * it, some weeks the winner hands it straight back, and handing it back can
 * mean two different things - the hosting bill, or the next weekly's pool.
 * Those are the three endings, and the row exists so that "did we settle
 * week 9" is a question with an answer.
 */
class CompPayout extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_DONATED_SITE = 'donated_site';
    public const STATUS_DONATED_COMPS = 'donated_comps';

    /** Statuses meaning nothing is owed to the winner any more. */
    public const RESOLVED_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_DONATED_SITE,
        self::STATUS_DONATED_COMPS,
    ];

    /** How each ending is written, everywhere it is shown. */
    public const LABELS = [
        self::STATUS_PENDING => 'Payout pending',
        self::STATUS_PAID => 'Paid out',
        self::STATUS_DONATED_SITE => 'Donated to the website',
        self::STATUS_DONATED_COMPS => 'Donated to the next comps',
    ];

    protected $fillable = [
        'comp_round_id',
        'physics',
        'user_id',
        'amount',
        'status',
        'site_donation_id',
        'resolved_at',
        'resolved_by',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(CompRound::class, 'comp_round_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(SiteDonation::class, 'site_donation_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return in_array($this->status, self::RESOLVED_STATUSES, true);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
