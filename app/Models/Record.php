<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Record extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'country',
        'physics',
        'mode',
        'gametype',
        'time',
        'date_set',
        'mdd_id',
        'user_id',
        'mapname',
        'rank',
        'besttime'
    ];

    public function user () {
        return $this->belongsTo(User::class, 'mdd_id', 'mdd_id')->select('id', 'name', 'profile_photo_path', 'country', 'mdd_id', 'model');
    }

    public function map () {
        return $this->belongsTo(Map::class, 'mapname', 'name');
    }

    public function uploadedDemos () {
        return $this->hasMany(UploadedDemo::class, 'record_id', 'id');
    }

    /**
     * Boot method to clear profile cache when records change
     */
    protected static function boot() {
        parent::boot();

        // Clear cache when record is saved (created or updated)
        static::saved(function ($record) {
            if ($record->mdd_id) {
                self::clearProfileCache($record->mdd_id);
            }
        });

        // Clear cache when record is deleted
        static::deleted(function ($record) {
            if ($record->mdd_id) {
                self::clearProfileCache($record->mdd_id);
            }
        });
    }

    /**
     * Clear all profile cache for a player
     */
    protected static function clearProfileCache($mddId) {
        Cache::forget("profile:competitors:{$mddId}:cpm");
        Cache::forget("profile:competitors:{$mddId}:vq3");
        Cache::forget("profile:rivals:{$mddId}:cpm");
        Cache::forget("profile:rivals:{$mddId}:vq3");

        \Log::info("Profile cache cleared for player {$mddId}");
    }
}
