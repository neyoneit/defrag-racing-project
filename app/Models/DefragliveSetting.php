<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefragliveSetting extends Model
{
    protected $table = 'defraglive_settings';

    protected $fillable = [
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /** Default game settings (parity with the bridge's get_current_settings fallback). */
    public const DEFAULTS = [
        'brightness' => 2, 'picmip' => 0, 'fullbright' => false, 'gamma' => 1.2,
        'sky' => true, 'triggers' => false, 'clips' => false, 'slick' => false,
        'drawgun' => false, 'angles' => false, 'lagometer' => false, 'snaps' => true,
        'cgaz' => true, 'speedinfo' => true, 'speedorig' => false, 'inputs' => true,
        'obs' => false, 'nodraw' => false, 'thirdperson' => false, 'miniview' => false,
        'gibs' => false, 'blood' => false,
    ];

    public static function current(): array
    {
        return static::find(1)?->payload ?? self::DEFAULTS;
    }

    /** Race-safe singleton upsert (id=1), merging into whatever is stored. */
    public static function store(array $settings): void
    {
        $merged = array_merge(static::current(), $settings);

        static::upsert(
            [[
                'id' => 1,
                'payload' => json_encode($merged),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['id'],
            ['payload', 'updated_at']
        );
    }
}
