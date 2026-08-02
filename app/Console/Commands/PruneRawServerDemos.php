<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Drop index rows for raw demos that have since been packed.
 *
 * Packing a demo on the storage box replaces `<name>.dm_68` with
 * `<name>.dm_68.7z`. The indexer sees the archive and inserts a row for it,
 * but the row for the file that is gone stays: `serverdemos:index --sweep`
 * only clears `on_contabo`, it never deletes. Left alone, the backfill of the
 * ~116k demos that predate packing would leave two rows per demo and
 * `ServerDemo::forRecord()` could hand a moderator the dead one - a path to a
 * file that is not on disk any more.
 *
 * A row is removed only when a row for exactly `path . '.7z'` exists, so the
 * pairing is by identity, not by guesswork. And if this ever removed a row
 * whose raw file is in fact still there, the next index run puts it back.
 */
class PruneRawServerDemos extends Command
{
    protected $signature = 'serverdemos:prune-raw {--dry-run : Only report what would be deleted}';

    protected $description = 'Remove server_demos rows for raw demos that now exist as .7z';

    /** Deleted in batches so a backfill-sized cleanup is not one huge transaction. */
    private const BATCH = 1000;

    public function handle(): int
    {
        $pairs = $this->pairedRawQuery()->count();

        if ($pairs === 0) {
            $this->info('Nothing to prune - no raw row has a packed counterpart.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Would delete {$pairs} raw row(s) that have a .7z counterpart.");
            $this->line('Sample:');
            foreach ($this->pairedRawQuery()->limit(5)->pluck('path') as $path) {
                $this->line('  ' . $path);
            }
            return self::SUCCESS;
        }

        // Ids first, delete second. MySQL refuses to delete from a table that
        // a subquery in the same statement reads (error 1093), and the pairing
        // is exactly such a subquery. Selecting is fine; only the delete has
        // to be kept away from it.
        $deleted = 0;
        do {
            $ids = $this->pairedRawQuery()->limit(self::BATCH)->pluck('raw.id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += DB::table('server_demos')->whereIn('id', $ids)->delete();
            $this->line("  deleted {$deleted}/{$pairs}");
        } while (true);

        $this->info("Pruned {$deleted} raw row(s).");

        return self::SUCCESS;
    }

    /**
     * Raw rows whose packed counterpart is already indexed. `path` is unique,
     * so the correlated lookup is an index hit per row rather than a scan.
     */
    private function pairedRawQuery()
    {
        return DB::table('server_demos as raw')
            ->where('raw.path', 'not like', '%.7z')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('server_demos as packed')
                    ->whereRaw("packed.path = CONCAT(raw.path, '.7z')");
            });
    }
}
