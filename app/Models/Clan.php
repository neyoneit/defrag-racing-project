<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'plain_name',
        'image',
        'admin_id',
        'hidden',
        'banned'
    ];

    protected $casts = [
        'featured_stats' => 'array'
    ];

    protected $attributes = [
        'featured_stats' => '[]'
    ];

    /**
     * Boot method to automatically generate plain_name from name
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($clan) {
            // Automatically strip Quake 3 color codes from name to generate plain_name
            $clan->plain_name = preg_replace('/\^[0-9]/', '', $clan->name);
        });
    }

    public function players()
    {
        return $this->hasMany(ClanPlayer::class);
    }

    public function invitations()
    {
        return $this->hasMany(ClanInvitation::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
