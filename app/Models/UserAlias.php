<?php

namespace App\Models;

use App\Services\NameMatcher;
use Illuminate\Database\Eloquent\Model;

class UserAlias extends Model
{
    protected $fillable = ['user_id', 'mdd_id', 'alias', 'alias_colored', 'usage_count', 'source', 'is_approved'];

    protected $casts = [
        'is_approved' => 'boolean',
        'usage_count' => 'integer',
    ];

    /**
     * Invalidate NameMatcher cache whenever an alias is added, edited or
     * removed so the in-memory + Cache::-backed alias index reflects the
     * change immediately. Without this, admins adding a new alias via the
     * Filament UI would have to wait up to 5 minutes (cache TTL) before a
     * rematch run can pick it up.
     */
    protected static function booted(): void
    {
        $invalidate = function () {
            app(NameMatcher::class)->clearCache();
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mddProfile()
    {
        return $this->belongsTo(MddProfile::class, 'mdd_id');
    }

    public function reports()
    {
        return $this->hasMany(AliasReport::class, 'alias_id');
    }
}
