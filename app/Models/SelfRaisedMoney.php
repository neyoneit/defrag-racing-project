<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SelfRaisedMoney extends Model
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
