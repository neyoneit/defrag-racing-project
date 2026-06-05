<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A DefragLive watch-time contest window. Winner is drawn by a watch-time-
 * weighted raffle over the watch sessions in [starts_at, ends_at]
 * (DefragliveWatchService::draw). Default $5 / 2 weeks, but per-row.
 */
class DefragliveContest extends Model
{
    protected $table = 'defraglive_contests';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'prize_amount',
        'prize_currency',
        'status',
        'winner_mdd_id',
        'winner_user_id',
        'winner_name',
        'winner_seconds',
        'winner_tickets',
        'total_tickets',
        'winning_ticket',
        'drawn_at',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'drawn_at' => 'datetime',
        'prize_amount' => 'decimal:2',
        'winner_seconds' => 'integer',
        'winner_tickets' => 'integer',
        'total_tickets' => 'integer',
        'winning_ticket' => 'integer',
    ];

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function isOpenForWatching(): bool
    {
        $now = now();

        return $this->starts_at && $this->ends_at
            && $now->betweenIncluded($this->starts_at, $this->ends_at);
    }

    /** The contest currently accepting watch time (drives the public page). */
    public function scopeCurrent($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByDesc('starts_at');
    }
}
