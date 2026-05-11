<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class ApiCallLog extends Model
{
    use Prunable;

    protected $table = 'api_call_log';
    public $timestamps = false; // Only created_at, no updated_at

    protected $fillable = [
        'user_id',
        'token_id',
        'route',
        'query_string',
        'method',
        'ip',
        'response_status',
        'response_ms',
        'created_at',
    ];

    protected $casts = [
        'created_at'      => 'datetime',
        'response_status' => 'integer',
        'response_ms'     => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function token()
    {
        return $this->belongsTo(\Laravel\Sanctum\PersonalAccessToken::class, 'token_id');
    }

    /**
     * Prunable retention: keep 30 days. `php artisan model:prune` runs
     * daily via Console\Kernel and deletes everything older.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(30));
    }
}
