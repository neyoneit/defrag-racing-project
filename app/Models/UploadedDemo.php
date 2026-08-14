<?php

namespace App\Models;

use App\Models\Scopes\HidesUnreleasedCompsDemos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class UploadedDemo extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        // A comps entry is an ordinary demo that must not be public while its
        // round is being played - the demo IS the route. Hidden by default so
        // every reader of this table is safe without knowing comps exists;
        // see HidesUnreleasedCompsDemos and withUnreleasedComps() below.
        static::addGlobalScope(new HidesUnreleasedCompsDemos());

        $clearCache = function ($demo) {
            Cache::forget('demo_counts_browse');
            Cache::forget('home:total_demos');
            if ($demo->user_id) {
                Cache::forget("demo_counts_user_{$demo->user_id}");
                Cache::forget("profile:assigned_demos:{$demo->user_id}");
                Cache::forget("profile:demo_stats:{$demo->user_id}");
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }

    protected $fillable = [
        'original_filename',
        'processed_filename',
        'file_path',
        'file_size',
        'file_hash',
        'user_id',
        'record_id',
        'map_name',
        'physics',
        'gametype',
        'time_ms',
        'player_name',
        'q3df_login_name',
        'q3df_login_name_colored',
        'country',
        'record_date',
        'validity',
        'status',
        'comps_hidden_until',
        'source',
        'processing_output',
        'name_confidence',
        'match_method',
        'suggested_user_id',
        'assigned_user_id',
        'assigned_by_user_id',
        'assigned_user_at',
        'matched_alias',
        'manually_assigned',
        'download_count',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'time_ms' => 'integer',
        'record_date' => 'datetime',
        'validity' => 'array',
        'name_confidence' => 'integer',
        'manually_assigned' => 'boolean',
        'download_count' => 'integer',
        'comps_hidden_until' => 'datetime',
    ];

    /**
     * User who uploaded the demo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associated online record
     */
    public function record()
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * Associated offline record
     */
    public function offlineRecord()
    {
        return $this->hasOne(OfflineRecord::class, 'demo_id');
    }

    /**
     * Assignment reports for this demo
     */
    public function assignmentReports()
    {
        return $this->hasMany(DemoAssignmentReport::class, 'demo_id');
    }

    /**
     * Suggested user based on name matching
     */
    public function suggestedUser()
    {
        return $this->belongsTo(User::class, 'suggested_user_id');
    }

    /**
     * The account a human decided this demo belongs to. Only ever set by staff,
     * and it outranks the alias resolver: somebody looked at the demo and said
     * so. See the 2026_08_09 migration for why it is not user_id.
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function renderedVideo()
    {
        return $this->hasOne(RenderedVideo::class, 'demo_id');
    }

    /**
     * Check if demo is offline (df/fs/fc) vs online (mdf/mfs/mfc)
     */
    public function getIsOfflineAttribute()
    {
        // Online demos have gametype starting with 'm' (mdf, mfs, mfc)
        // Offline demos don't (df, fs, fc)
        return $this->gametype && !str_starts_with($this->gametype, 'm');
    }

    /**
     * Get the full storage path
     */
    public function getFullPathAttribute()
    {
        return storage_path('app/' . $this->file_path);
    }

    /**
     * Get formatted time
     */
    public function getFormattedTimeAttribute()
    {
        if (!$this->time_ms) {
            return null;
        }

        $minutes = floor($this->time_ms / 60000);
        $seconds = floor(($this->time_ms % 60000) / 1000);
        $milliseconds = $this->time_ms % 1000;

        return sprintf('%d:%02d.%03d', $minutes, $seconds, $milliseconds);
    }

    /**
     * Check if demo is from online/multiplayer run
     */
    public function getIsOnlineAttribute()
    {
        // Online gametypes start with 'm': mdf, mfs, mfc
        return $this->gametype && str_starts_with($this->gametype, 'm');
    }

    /**
     * Increment download counter
     */
    public function incrementDownloads()
    {
        $this->increment('download_count');
    }

    /**
     * Include comps entries whose round is still running.
     *
     * Two kinds of caller want this, and only two.
     *
     * The first shows the demo to somebody entitled to see it: an admin
     * reviewing a reported run, the comps page listing somebody their own
     * entries, the download when one of those asks for the file.
     *
     * The second only needs to know the row EXISTS, and would do damage
     * without it - every duplicate check before an upload. `file_hash` is
     * unique, so a dedupe query that cannot see a comps entry does not
     * politely miss it; it decides the file is new and publishes a run that
     * is still being competed on, or dies on the constraint. Those callers
     * must answer "already here" and must not repeat what they saw.
     *
     * Everywhere else the default is the correct answer.
     */
    public function scopeWithUnreleasedComps($query)
    {
        return $query->withoutGlobalScope(HidesUnreleasedCompsDemos::class);
    }

    /** True while this demo is a comps entry in a round still being played. */
    public function isHeldForComps(): bool
    {
        return $this->comps_hidden_until !== null
            && $this->comps_hidden_until->isFuture();
    }

    /**
     * Route binding resolves held comps entries too, so the controller can tell
     * "this does not exist" apart from "this is not yours yet" and answer 403
     * rather than 404. Every route bound to this model is either admin-only or
     * checks isHeldForComps() itself - see DemosController::download.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::withUnreleasedComps()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }
}
