<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SftpCredential extends Model
{
    protected $fillable = [
        'user_id',
        'application_id',
        'sftp_username',
        'host',
        'port',
        'remote_path',
        'servers',
        'password_pending',
        'status',
        'provisioned_at',
        'revoked_at',
        'provisioned_by',
    ];

    protected $casts = [
        'provisioned_at'   => 'datetime',
        'revoked_at'       => 'datetime',
        // Encrypted-at-rest; written by Approve / Reset, nulled by the
        // user's "I've copied it" acknowledgement.
        'password_pending' => 'encrypted',
        // Mutable working set of declared servers — admin fills rs_code
        // per row from Filament after approval.
        'servers'          => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function application()
    {
        return $this->belongsTo(ServerOwnerApplication::class);
    }

    public function provisioner()
    {
        return $this->belongsTo(User::class, 'provisioned_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
