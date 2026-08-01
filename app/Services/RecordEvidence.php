<?php

namespace App\Services;

use App\Models\Record;
use App\Models\ServerDemo;
use App\Models\UploadedDemo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Everything a moderator can look at when a record is flagged.
 *
 * People flag a record, not a demo - they have no way of knowing what
 * evidence exists behind it. Finding that out is this class's job, and it
 * pulls from two unrelated places:
 *
 * - **public uploads**: someone uploaded a demo and it was matched to this
 *   record (`uploaded_demos.record_id`)
 * - **serverdemos**: the run happened on one of our servers, so the
 *   recordsystem wrote a demo of it. That exists whether or not anybody
 *   uploaded anything, and the player never had a chance to pick which run
 *   to show - which is exactly what makes it worth having
 *
 * On top of that it returns the player's other serverdemos on the same map.
 * A single run rarely proves anything by itself; a jump from a plausible
 * time to an implausible one, or a first-ever attempt that lands on a world
 * record, is what a human actually spots. Until record time history arrives
 * from the MDD database, this is the closest thing we have to it, and it is
 * real evidence rather than a derived number.
 */
class RecordEvidence
{
    /** Enough to see how a player got to a time without loading a career. */
    private const HISTORY_LIMIT = 50;

    /** Same five minutes the storage browsers hand out. */
    private const LINK_MINUTES = 5;

    /**
     * @return array{
     *   serverdemo: ?ServerDemo,
     *   history: Collection<int, ServerDemo>,
     *   uploads: Collection<int, UploadedDemo>
     * }
     */
    public static function for(Record $record, ?UploadedDemo $flaggedDemo = null): array
    {
        $serverdemo = ServerDemo::forRecord($record)
            ->orderByDesc('recorded_at')
            ->first();

        $uploads = UploadedDemo::where('record_id', $record->id)
            ->orderByDesc('created_at')
            ->get();

        // A flag can name a demo that was never matched to this record.
        // It was still what the reporter was looking at, so it belongs here.
        if ($flaggedDemo && ! $uploads->contains('id', $flaggedDemo->id)) {
            $uploads->prepend($flaggedDemo);
        }

        return [
            'serverdemo' => $serverdemo,
            'history' => self::history($record, $serverdemo),
            'uploads' => $uploads,
        ];
    }

    /**
     * The player's other runs on this map, newest first. The flagged run
     * itself is left out - it is shown on its own above.
     */
    private static function history(Record $record, ?ServerDemo $exclude): Collection
    {
        if (! $record->mdd_id || ! $record->mapname) {
            return collect();
        }

        return ServerDemo::query()
            ->where('map_name', $record->mapname)
            ->where('mdd_id', $record->mdd_id)
            ->when($record->physics, fn ($q, $physics) => $q->where('physics', strtolower($physics)))
            ->when($record->mode, fn ($q, $mode) => $q->where('mode', strtolower($mode)))
            ->when($exclude, fn ($q, $demo) => $q->where('id', '!=', $demo->id))
            ->orderByDesc('recorded_at')
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    /**
     * A short-lived signed link to one serverdemo. Nothing else may produce
     * these: the bucket and the storage VPS are both private, and this is the
     * only door.
     */
    public static function downloadUrl(ServerDemo $demo): string
    {
        return URL::temporarySignedRoute(
            'defraghq.storage-download',
            now()->addMinutes(self::LINK_MINUTES),
            ['disk' => 'serverdemos', 'path' => $demo->path],
        );
    }
}
