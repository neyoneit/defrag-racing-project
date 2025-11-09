<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScraperProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'current_page',
        'detected_last_page',
        'records_scraped',
        'last_scrape_at',
        'status',
        'error_message',
        'stop_reason',
    ];

    protected $casts = [
        'last_scrape_at' => 'datetime',
    ];
}
