<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAlias extends Model
{
    protected $fillable = ['user_id', 'mdd_id', 'alias', 'alias_colored', 'usage_count', 'source', 'is_approved'];

    protected $casts = [
        'is_approved' => 'boolean',
        'usage_count' => 'integer',
    ];

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
