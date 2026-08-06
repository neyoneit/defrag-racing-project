<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A time its own owner withdrew. See the migration for why it is a snapshot.
 */
class PlayerSelfReport extends Model
{
    protected $fillable = [
        'user_id',
        'mdd_id',
        'player_name',
        'record_id',
        'mapname',
        'physics',
        'mode',
        'gametype',
        'time',
        'reason',
        'note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function map()
    {
        return $this->belongsTo(Map::class, 'mapname', 'name');
    }
}
