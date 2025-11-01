<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliasReport extends Model
{
    protected $fillable = [
        'alias_id',
        'reported_by_user_id',
        'reason',
        'status',
        'resolved_by_admin_id',
        'resolved_at',
        'admin_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function alias()
    {
        return $this->belongsTo(UserAlias::class, 'alias_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }
}
