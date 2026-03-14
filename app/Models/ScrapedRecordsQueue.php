<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapedRecordsQueue extends Model
{
    use HasFactory;

    protected $table = 'scraped_records_queue';

    protected $fillable = [
        'page_number',
        'record_index',
        'record_data',
        'status',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'record_data' => 'array',
    ];
}
