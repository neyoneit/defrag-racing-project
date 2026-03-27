<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'area',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public static function log(string $area, string $action, $subject = null, ?array $metadata = null): static
    {
        return static::create([
            'user_id' => auth()->id(),
            'area' => $area,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
