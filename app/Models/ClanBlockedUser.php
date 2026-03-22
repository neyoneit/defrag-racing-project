<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClanBlockedUser extends Model
{
    protected $fillable = [
        'clan_id',
        'user_id',
    ];

    public function clan()
    {
        return $this->belongsTo(Clan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
