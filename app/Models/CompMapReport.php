<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Somebody found a candidate they cannot finish in one of the physics.
 *
 * `physics` is the one it cannot be done in. Approved, the map gains a
 * cpmonly / vq3only tag, drops off that ballot immediately, and never enters
 * that physics' pool again.
 */
class CompMapReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'comp_round_id',
        'map_id',
        'physics',
        'reported_by',
        'status',
    ];

    public function round()
    {
        return $this->belongsTo(CompRound::class, 'comp_round_id');
    }

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
