<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerdemoValidationComment extends Model
{
    protected $fillable = [
        'validation_case_id',
        'record_flag_id',
        'user_id',
        'body',
        'event',
    ];

    public function validationCase()
    {
        return $this->belongsTo(ServerdemoValidationCase::class, 'validation_case_id');
    }

    /** Kept for the notes written before comments moved onto the case. */
    public function flag()
    {
        return $this->belongsTo(RecordFlag::class, 'record_flag_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
