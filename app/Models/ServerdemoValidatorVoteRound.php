<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerdemoValidatorVoteRound extends Model
{
    protected $fillable = ['title', 'opened_at', 'closed_at', 'opened_by'];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function votes()
    {
        return $this->hasMany(ServerdemoValidatorVote::class, 'round_id');
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** The round people can vote in right now, if there is one. */
    public static function current(): ?self
    {
        return static::whereNull('closed_at')->latest('opened_at')->first();
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
