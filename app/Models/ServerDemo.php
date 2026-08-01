<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One demo the recordsystem wrote on a community server.
 *
 * These are NOT public. They exist so an admin - and, once the report
 * workflow lands, a moderator handling a specific report - can verify a
 * reported record. There is no public route to them and there must never be
 * one.
 */
class ServerDemo extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_dir',
        'sftp_credential_id',
        'path',
        'filename',
        'size',
        'recorded_at',
        'rs_server_id',
        'map_name',
        'physics',
        'time_ms',
        'mdd_id',
        'record_id',
        'on_contabo',
        'on_b2',
        'on_nas',
        'indexed_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'recorded_at' => 'datetime',
        'indexed_at' => 'datetime',
        'rs_server_id' => 'integer',
        'time_ms' => 'integer',
        'mdd_id' => 'integer',
        'on_contabo' => 'boolean',
        'on_b2' => 'boolean',
        'on_nas' => 'boolean',
    ];

    public function credential()
    {
        return $this->belongsTo(SftpCredential::class, 'sftp_credential_id');
    }

    public function record()
    {
        return $this->belongsTo(Record::class, 'record_id');
    }

    /**
     * The demo of exactly this record's run, if the record was set on one of
     * our servers. A record set elsewhere simply has none - that is an
     * answer, not a failure.
     */
    public function scopeForRecord($query, Record $record)
    {
        return $query
            ->where('map_name', $record->mapname)
            ->where('mdd_id', $record->mdd_id)
            ->where('time_ms', $record->time)
            ->when($record->physics, fn ($q, $physics) => $q->where('physics', strtolower($physics)));
    }
}
