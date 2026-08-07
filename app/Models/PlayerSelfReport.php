<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A time its own owner withdrew. See the migration for why it is a snapshot.
 */
class PlayerSelfReport extends Model
{
    protected $fillable = [
        'user_id',
        'mdd_id',
        'player_name',
        'record_id',
        'mapname',
        'physics',
        'mode',
        'gametype',
        'time',
        'reason',
        'note',
        'handling',
        'processed_at',
        'processed_by',
        'resolution',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * When the player wants it handled. `mode` was already taken by the game
     * mode of the run, so the column is `handling` - naming it `mode` twice
     * would have been a bug waiting for whoever reads this next.
     */
    public const HANDLING = [
        'immediate' => 'Hide it here as soon as an admin approves',
        'on_merge' => 'Wait for the MDD merge and do both at once',
    ];

    public const RESOLUTIONS = [
        'hidden' => 'Hidden by an admin',
        'beaten' => 'Beaten by a new run',
    ];

    public function scopePending($query)
    {
        return $query->whereNull('processed_at');
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function wasBeaten(): bool
    {
        return $this->resolution === 'beaten';
    }

    /** What the player sees as the state of their request. */
    public function stateLabel(): string
    {
        if ($this->wasBeaten()) {
            return 'Beaten - resolved';
        }

        if ($this->isProcessed()) {
            return 'Off the board';
        }

        return $this->handling === 'immediate'
            ? 'Waiting for an admin'
            : 'Queued for the MDD merge';
    }

    /**
     * The serverdemo of this run, if it was set on one of our servers.
     *
     * Matched from the snapshot rather than through the record: hiding the run
     * soft-deletes it, and the withdrawal has to stay reviewable afterwards.
     * Same key the validation queue uses - map, player, time, physics, mode.
     */
    public function serverDemo(): ?ServerDemo
    {
        return $this->memo('serverDemo', fn () => ServerDemo::query()
            ->where('map_name', $this->mapname)
            ->where('mdd_id', $this->mdd_id)
            ->where('time_ms', $this->time)
            ->when($this->physics, fn ($q, $p) => $q->where('physics', strtolower($p)))
            ->when($this->mode, fn ($q, $m) => $q->where('mode', strtolower($m)))
            ->first());
    }

    /**
     * The demo the player uploaded here, if any.
     *
     * Tried by record first, then by the run itself, because deleting a record
     * detaches its uploaded demos (Record::deleting) - so after the run is
     * hidden the link is gone and only the map, time and player are left.
     */
    public function uploadedDemo(): ?UploadedDemo
    {
        return $this->memo('uploadedDemo', function () {
            if ($this->record_id) {
                $byRecord = UploadedDemo::where('record_id', $this->record_id)->first();

                if ($byRecord) {
                    return $byRecord;
                }
            }

            return UploadedDemo::query()
                ->where('map_name', $this->mapname)
                ->where('time_ms', $this->time)
                ->where(function ($q) {
                    $q->where('user_id', $this->user_id)
                        ->orWhere('player_name', $this->player_name);
                })
                ->first();
        });
    }

    /** A published YouTube render of this run, if one exists. */
    public function video(): ?RenderedVideo
    {
        return $this->memo('video', fn () => RenderedVideo::query()
            ->whereNotNull('youtube_url')
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->where('record_id', $this->record_id)
                    ->orWhere(fn ($inner) => $inner
                        ->where('map_name', $this->mapname)
                        ->where('time_ms', $this->time)
                        ->where('player_name', $this->player_name));
            })
            ->first());
    }

    /**
     * Each lookup runs once per row. The admin table asks the same question in
     * the badge column and again in the download action.
     */
    private function memo(string $key, \Closure $resolve)
    {
        static $cache = [];

        $id = $key . ':' . $this->getKey();

        if (! array_key_exists($id, $cache)) {
            $cache[$id] = $resolve();
        }

        return $cache[$id];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function map()
    {
        return $this->belongsTo(Map::class, 'mapname', 'name');
    }
}
