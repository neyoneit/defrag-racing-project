<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteDonation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'donor_name',
        'donor_email',
        'amount',
        'currency',
        'donation_date',
        'note',
        'status',
    ];

    protected $casts = [
        'donation_date' => 'date',
        'amount' => 'decimal:2',
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
}
