<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAlias extends Model
{
    protected $fillable = ['user_id', 'alias', 'is_approved'];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reports()
    {
        return $this->hasMany(AliasReport::class, 'alias_id');
    }
}
