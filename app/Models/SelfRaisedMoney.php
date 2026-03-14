<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SelfRaisedMoney extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'amount',
        'currency',
        'earned_date',
        'description',
    ];

    protected $casts = [
        'earned_date' => 'date',
        'amount' => 'decimal:2',
    ];
}
