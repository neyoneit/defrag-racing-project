<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnlinePlayer extends Model
{
    use HasFactory;

    protected $with = ['profile.clan'];

    protected $fillable = [
        'name',
        'time',
        'client_id',
        'mdd_id',
        'nospec',
        'country',
        'follow_num',
        'server_id',
        'model',
        'headmodel'
    ];

    public function spectators() {
        return $this->hasMany(OnlinePlayer::class, 'follow_num', 'client_id');
    }

    /**
     * The column list is a whitelist, not a tidy-up: this relation is
     * serialized into the public server list, and handing out a whole User
     * there once leaked email addresses and live OAuth tokens. Add a column
     * only if a stranger may read it.
     *
     * `is_live` and `twitch_name` qualify - a Twitch channel is public by
     * definition, and whether it is streaming is what the dot beside the name
     * in the server list is showing.
     */
    public function profile() {
        return $this->belongsTo(User::class, 'mdd_id', 'mdd_id')->select('id', 'name', 'profile_photo_path', 'country', 'mdd_id', 'model', 'twitch_name', 'is_live');
    }
}
