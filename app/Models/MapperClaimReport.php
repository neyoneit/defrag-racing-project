<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapperClaimReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'mapper_claim_id',
        'reason',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function claim()
    {
        return $this->belongsTo(MapperClaim::class, 'mapper_claim_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
