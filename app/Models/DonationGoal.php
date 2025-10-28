<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'yearly_goal',
        'currency',
    ];

    protected $casts = [
        'yearly_goal' => 'decimal:2',
    ];
}
