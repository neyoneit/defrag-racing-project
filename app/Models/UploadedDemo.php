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

        // Which record a demo belongs to decides whether Demos Top shows it as
        // a row of its own or folds it into the main record's cluster - so the
        // moment it moves, the cached Demos Top for that map is wrong.
        //
        // Here rather than at the callers. Five places move a demo between
        // records: two assign endpoints, two unassign paths and the admin's
        // reassignment action. Exactly one of them remembered to bump the
        // counter, so a demo assigned to an existing record stayed on screen
        // twice - once folded into the record it now belongs to, once as the
        // orphan row the hour-old cache still believed in. Anywhere but the
        // model, the sixth caller forgets again.
        //
        // `wasChanged` keeps it to the writes that matter: a download counter
        // or an assignment note leaves the cache alone.
        // `comps_hidden_until` moves a demo in and out of Demos Top for the
        // same reason: a held run is kept out of the offline pool while its
        // round is being played, so both the moment it is held and the moment
        // the round releases it change what that map should show.
        static::saved(function ($demo) {
            if (! $demo->wasChanged('record_id') && ! $demo->wasChanged('comps_hidden_until')) {
                return;
            }

            // The map as it was before this write, when the write cleared it.
            // Reprocessing a demo nulls map_name and record_id together, and
            // the stale row to clear is filed under the OLD map - reading the
            // new value there finds null and silently skips the bump.
            $map = $demo->map_name ?: $demo->getOriginal('map_name');

            static::forgetDemosTop($map);
        });

        // A deleted demo leaves the same stale row behind.
        static::deleted(function ($demo) {
            if ($demo->record_id || $demo->comps_hidden_until) {
                static::forgetDemosTop($demo->map_name);
            }
        });
    }

    /**
     * Retire the cached Demos Top for one map.
     *
     * A counter rather than a tagged flush: tags do not flush reliably under
     * Redis behind Octane, and a Demos Top that survives its own invalidation
     * is worse than no cache at all - the page then shows live records beside
     * an hour-old cluster and every action looks half-applied.
     *
     * Public and static because the model's own events cannot carry this
     * alone. The comps hold and release write with `saveQuietly()` and with
     * query-builder `update()`, both of which skip events on purpose, so those
     * paths call this by hand. Anything that moves a demo between records, or
     * in or out of a comps hold, owes this map one call.
     */
    public static function forgetDemosTop(?string $mapName): void
    {
        if ($mapName) {
            Cache::increment('demostop_gen:' . $mapName);
        }
    }

    /**
     * Which route put this file on the site.
     *
     * The column has existed since March 2026 with a default of `web`, but
     * only demome ever wrote to it, so every other route landed on the
     * default and the four of them were indistinguishable. That mattered the
     * first time somebody asked how a demo of theirs had reached comps: the
     * honest answer was that the database could not say.
     *
     * `WEB` keeps its old meaning, so rows written before 18 Aug 2026 read
     * the same as they always did - which for those rows means "the /demos
     * form, the launcher, an archive or comps", not "the /demos form".
     */
    public const SOURCE_WEB = 'web';
    public const SOURCE_LAUNCHER = 'launcher';
    public const SOURCE_COMPS = 'comps';
    public const SOURCE_ARCHIVE = 'archive';
    public const SOURCE_DEMOME = 'demome';

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
        'client_file_mtime',
        'validity',
        'status',
        'comps_hidden_until',
        'comps_withdrawn_at',
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

    /**
     * Serialised with every demo so a list can say why one of them is not
     * where its owner expects it to be.
     *
     * Cheap - it reads two columns already on the row - and null for the
     * overwhelming majority, which is the point: only a demo comps is holding
     * has anything to explain.
     */
    protected $appends = [
        'comps_hold',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'time_ms' => 'integer',
        'record_date' => 'datetime',
        'client_file_mtime' => 'datetime',
        'validity' => 'array',
        'name_confidence' => 'integer',
        'manually_assigned' => 'boolean',
        'download_count' => 'integer',
        'comps_hidden_until' => 'datetime',
        'comps_withdrawn_at' => 'datetime',
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
    /**
     * Why this demo is missing from the public site: `held`, `withdrawn`, or
     * null when it is not missing at all.
     *
     * Somebody who uploads a run of the map comps is playing gets it taken off
     * the site until the round ends. That is correct and it is the whole point
     * of the hold - but it was also invisible. The demo vanished from the
     * uploader's own list with nothing said, so the only reading available was
     * that the upload had failed. The file was never gone: download has let
     * the owner and an admin through from the start. There was simply nothing
     * left to click.
     *
     * `withdrawn` outranks `held` because it answers the better question. A
     * person who took their own run out of comps wants to see that they did,
     * not to be told the site is holding it.
     */
    public function getCompsHoldAttribute(): ?string
    {
        if (! $this->isHeldForComps()) {
            return null;
        }

        return $this->comps_withdrawn_at ? 'withdrawn' : 'held';
    }

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
