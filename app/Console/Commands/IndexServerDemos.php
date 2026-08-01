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

        $totals = ['seen' => 0, 'unparsed' => 0, 'gone' => 0, 'failed' => 0];

        foreach ($roots as $root) {
            $this->line("Indexing {$root}/ ...");

            $batch = [];
            $seen = 0;
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

                    $batch[] = [
                        'owner_dir'          => $parsed['owner_dir'],
                        'sftp_credential_id' => $credentials[$parsed['owner_dir']] ?? null,
                        'path'               => $item->path(),
                        'filename'           => $parsed['filename'],
                        'size'               => (int) ($item->fileSize() ?? 0),
                        'recorded_at'        => $item->lastModified()
                            ? Carbon::createFromTimestamp($item->lastModified())
                            : null,
                        'rs_server_id'       => $parsed['rs_server_id'],
                        'map_name'           => $parsed['map_name'],
                        'physics'            => $parsed['physics'],
                        'time_ms'            => $parsed['time_ms'],
                        'mdd_id'             => $parsed['mdd_id'],
                        'on_contabo'         => true,
                        'indexed_at'         => $startedAt,
                        'created_at'         => $startedAt,
                        'updated_at'         => $startedAt,
                    ];

                    $seen++;

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

            // Anything in this directory the walk did not touch is no longer
            // on the local disk.
            $gone = 0;
            if (! $dry) {
                $gone = ServerDemo::where('owner_dir', $root)
                    ->where('on_contabo', true)
                    ->where(fn ($q) => $q->whereNull('indexed_at')->orWhere('indexed_at', '<', $startedAt))
                    ->update(['on_contabo' => false, 'updated_at' => Carbon::now()]);
            }

            $this->line(sprintf(
                '  %d demo(s)%s%s',
                $seen,
                $unparsed ? ", {$unparsed} non-demo file(s) ignored" : '',
                $gone ? ", {$gone} no longer on the local disk" : ''
            ));

            $totals['seen'] += $seen;
            $totals['unparsed'] += $unparsed;
            $totals['gone'] += $gone;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d demo(s) across %d director%s.%s%s',
            $dry ? 'Would index' : 'Indexed',
            $totals['seen'],
            count($roots),
            count($roots) === 1 ? 'y' : 'ies',
            $totals['gone'] ? " {$totals['gone']} marked as gone from the local disk." : '',
            $totals['unparsed'] ? " {$totals['unparsed']} file(s) were not demos." : ''
        ));

        if ($totals['failed']) {
            $this->error("{$totals['failed']} director(y/ies) failed to list; their entries were left untouched.");
            return self::FAILURE;
        }

        return self::SUCCESS;
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
                'rs_server_id', 'map_name', 'physics', 'time_ms', 'mdd_id',
                'on_contabo', 'indexed_at', 'updated_at',
            ]
        );
    }
}
