<?php

namespace App\Console\Commands;

use App\Models\ServerDemo;
use App\Models\SftpCredential;
use App\Services\ServerDemoPath;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Walks the serverdemo store on the storage VPS and records every demo in
 * `server_demos`.
 *
 * One recursive listing per credential directory: Flysystem's listContents()
 * carries size and mtime in the listing itself, so a directory with tens of
 * thousands of demos costs one traversal rather than a stat per file - the
 * same trick ServerdemosBrowser already relies on.
 *
 * Demos that were indexed before but are no longer on the disk are NOT
 * deleted from the index; they are marked `on_contabo = false`. Once the
 * local copy stops being the only copy, that flag is how the app knows to
 * serve the file from B2 instead. A directory whose listing threw is skipped
 * entirely rather than swept, so a dropped SSH connection can never look like
 * "every demo disappeared".
 */
class IndexServerDemos extends Command
{
    protected $signature = 'serverdemos:index
        {--dir=* : Only these credential directories}
        {--sweep : Also flag demos that are no longer on the local disk}
        {--dry-run : Report what would be indexed without writing}';

    protected $description = 'Index the serverdemos on the storage VPS into the server_demos table';

    private const DISK = 'serverdemos';

    /** Rows per upsert. Small enough to stay well inside max_allowed_packet. */
    private const BATCH = 500;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk(self::DISK);
        $startedAt = Carbon::now();

        try {
            $roots = $this->option('dir') ?: $disk->directories('');
        } catch (\Throwable $e) {
            $this->error('Could not list the serverdemos root: ' . $e->getMessage());
            return self::FAILURE;
        }

        $roots = array_values(array_filter(
            array_map(fn ($d) => trim($d, '/'), $roots),
            fn ($d) => $d !== '' && ! in_array($d, ServerDemoPath::SKIP_DIRS, true)
        ));

        if ($roots === []) {
            $this->warn('No credential directories found.');
            return self::SUCCESS;
        }

        $credentials = SftpCredential::pluck('id', 'sftp_username');
        $sweep = (bool) $this->option('sweep');

        $totals = ['seen' => 0, 'written' => 0, 'unparsed' => 0, 'gone' => 0, 'failed' => 0];

        foreach ($roots as $root) {
            $this->line("Indexing {$root}/ ...");

            // What the index already holds for this directory, so an
            // unchanged demo costs nothing. The traversal itself is ~14 s for
            // the whole store, cheap enough to run every few minutes - but
            // rewriting all 116k rows each time would not be.
            $known = $sweep ? [] : $this->knownRows($root);

            $batch = [];
            $seen = 0;
            $written = 0;
            $unparsed = 0;

            try {
                foreach ($disk->getDriver()->listContents($root, true) as $item) {
                    if (! $item->isFile()) {
                        continue;
                    }

                    $parsed = ServerDemoPath::parse($item->path());

                    if ($parsed === null) {
                        // Not a demo file. The ingest daemon deletes anything
                        // that is not a .dm_68, so this should be rare.
                        $unparsed++;
                        continue;
                    }

                    $seen++;

                    $size = (int) ($item->fileSize() ?? 0);
                    $mtime = $item->lastModified() ? (int) $item->lastModified() : null;

                    // Already indexed and untouched since. Skipping keeps a
                    // frequent run to only the demos that actually arrived.
                    $before = $known[$item->path()] ?? null;
                    if ($before !== null && $before['size'] === $size && $before['mtime'] === $mtime) {
                        continue;
                    }

                    $batch[] = [
                        'owner_dir'          => $parsed['owner_dir'],
                        'sftp_credential_id' => $credentials[$parsed['owner_dir']] ?? null,
                        'path'               => $item->path(),
                        'filename'           => $parsed['filename'],
                        'size'               => $size,
                        'recorded_at'        => $mtime ? Carbon::createFromTimestamp($mtime) : null,
                        'rs_server_id'       => $parsed['rs_server_id'],
                        'map_name'           => $parsed['map_name'],
                        'physics'            => $parsed['physics'],
                        'mode'               => $parsed['mode'],
                        'time_ms'            => $parsed['time_ms'],
                        'mdd_id'             => $parsed['mdd_id'],
                        'on_contabo'         => true,
                        'indexed_at'         => $startedAt,
                        'created_at'         => $startedAt,
                        'updated_at'         => $startedAt,
                    ];

                    $written++;

                    if (count($batch) >= self::BATCH) {
                        $this->flush($batch, $dry);
                        $batch = [];
                    }
                }
            } catch (\Throwable $e) {
                // Partial listing: keep whatever was already written, but do
                // NOT sweep - the missing files may simply never have been
                // listed.
                $this->flush($batch, $dry);
                $this->error("  listing failed after {$seen} file(s): " . $e->getMessage());
                $totals['seen'] += $seen;
                $totals['failed']++;
                continue;
            }

            $this->flush($batch, $dry);

            // Only a sweeping run may conclude that a demo is gone. A normal
            // run skips unchanged rows, so their indexed_at stays old and
            // every one of them would look missing.
            $gone = 0;
            if ($sweep && ! $dry) {
                $gone = ServerDemo::where('owner_dir', $root)
                    ->where('on_contabo', true)
                    ->where(fn ($q) => $q->whereNull('indexed_at')->orWhere('indexed_at', '<', $startedAt))
                    ->update(['on_contabo' => false, 'updated_at' => Carbon::now()]);
            }

            $this->line(sprintf(
                '  %d demo(s), %d written%s%s',
                $seen,
                $written,
                $unparsed ? ", {$unparsed} non-demo file(s) ignored" : '',
                $gone ? ", {$gone} no longer on the local disk" : ''
            ));

            $totals['seen'] += $seen;
            $totals['written'] += $written;
            $totals['unparsed'] += $unparsed;
            $totals['gone'] += $gone;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d demo(s) across %d director%s, %d new or changed.%s%s',
            $dry ? 'Would index' : 'Indexed',
            $totals['seen'],
            count($roots),
            count($roots) === 1 ? 'y' : 'ies',
            $totals['written'],
            $totals['gone'] ? " {$totals['gone']} marked as gone from the local disk." : '',
            $totals['unparsed'] ? " {$totals['unparsed']} file(s) were not demos." : ''
        ));

        if ($totals['failed']) {
            $this->error("{$totals['failed']} director(y/ies) failed to list; their entries were left untouched.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * path => size + mtime for one directory, so the walk can tell an
     * unchanged demo from a new one without writing anything. One select of
     * three narrow columns; even the largest credential is a few MB.
     */
    private function knownRows(string $root): array
    {
        $known = [];

        ServerDemo::where('owner_dir', $root)
            ->select('path', 'size', 'recorded_at')
            ->toBase()
            ->orderBy('id')
            ->chunk(20000, function ($rows) use (&$known) {
                foreach ($rows as $row) {
                    $known[$row->path] = [
                        'size' => (int) $row->size,
                        'mtime' => $row->recorded_at ? Carbon::parse($row->recorded_at)->getTimestamp() : null,
                    ];
                }
            });

        return $known;
    }

    private function flush(array $batch, bool $dry): void
    {
        if ($batch === [] || $dry) {
            return;
        }

        ServerDemo::upsert(
            $batch,
            ['path'],
            [
                'owner_dir', 'sftp_credential_id', 'filename', 'size', 'recorded_at',
                'rs_server_id', 'map_name', 'physics', 'mode', 'time_ms', 'mdd_id',
                'on_contabo', 'indexed_at', 'updated_at',
            ]
        );
    }
}
