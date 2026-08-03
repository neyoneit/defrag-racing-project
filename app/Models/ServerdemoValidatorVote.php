<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerdemoValidatorVote extends Model
{
    protected $fillable = ['round_id', 'application_id', 'voter_id'];

    public function round()
    {
        return $this->belongsTo(ServerdemoValidatorVoteRound::class, 'round_id');
    }

    public function application()
    {
        return $this->belongsTo(ServerdemoValidatorApplication::class, 'application_id');
    }

    public function voter()
    {
        return $this->belongsTo(User::class, 'voter_id');
    }
}
