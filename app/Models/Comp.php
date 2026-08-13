<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One competition. Weekly runs a single round over a week; season runs five
 * rounds of five days each. Everything below a comp behaves the same either
 * way, which is why there is one set of tables rather than two.
 */
class Comp extends Model
{
    use HasFactory;

    public const WEEKLY = 'weekly';
    public const SEASON = 'season';

    protected $fillable = [
        'type',
        'number',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function rounds()
    {
        return $this->hasMany(CompRound::class)->orderBy('index');
    }

    public function scopeWeekly($query)
    {
        return $query->where('type', self::WEEKLY);
    }

    public function scopeSeason($query)
    {
        return $query->where('type', self::SEASON);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** "Weekly #12". Not translated - the number is the name. */
    public function getTitleAttribute(): string
    {
        return ($this->type === self::SEASON ? 'Season #' : 'Weekly #') . $this->number;
    }

    public function isWeekly(): bool
    {
        return $this->type === self::WEEKLY;
    }
}
