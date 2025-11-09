<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapedPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_number',
        'records_count',
        'page_fingerprint',
        'status',
        'scraped_at',
        'processed_at',
    ];

    protected $casts = [
        'scraped_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
