<?php

namespace App\Console\Commands;

use App\Models\Download;
use App\Models\DownloadFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only inventory of the download folders that still sit on the storage
 * VPS instead of the community bucket.
 *
 * The hub was built so that community uploads land on B2 and only the locked
 * auto-synced articles point at dl_storage, but the pre-hub content never
 * moved: `downloads:migrate-bundles` brought the legacy rows over as plain
 * `external_url` links to dl.defrag.racing, and the files themselves stayed
 * put. Before any of it is copied to B2 we need to know how much of it is
 * still referenced at all - a folder that nothing links to is not worth
 * uploading.
 *
 * Writes nothing. Every number here comes from an SFTP listing plus the
 * downloads tables.
 */
class AuditLegacyDownloads extends Command
{
    protected $signature = 'downloads:audit-legacy
        {--folder=* : Limit the audit to these top-level folders}
        {--orphans : List every file nothing in the database references}
        {--broken : List every database entry whose file is missing on disk}';

    protected $description = 'Inventory the legacy download folders on the storage VPS against what the downloads hub references';

    private const DISK = 'dl_storage';

    /** Top-level folders under /www/downloads that hold pre-hub content. */
    private const FOLDERS = ['repacks', 'upscaled', 'game-bundles', 'useful-pk3s'];

    /** Hosts whose URLs resolve to this disk. */
    private const HOSTS = ['dl.defrag.racing'];

    private const URL_PREFIX = '/downloads/';

    public function handle(): int
    {
        $folders = $this->option('folder') ?: self::FOLDERS;
        $disk = Storage::disk(self::DISK);

        $referenced = $this->referencedPaths();
        $this->line(sprintf(
            'Database references %d distinct path(s) on this disk.',
            count($referenced)
        ));

        $seen = [];
        $rows = [];
        $orphanList = [];
        $totals = ['files' => 0, 'bytes' => 0, 'hitFiles' => 0, 'hitBytes' => 0];

        foreach ($folders as $folder) {
            $folder = trim($folder, '/');
            $this->line("Listing {$folder}/ ...");

            $files = 0;
            $bytes = 0;
            $hitFiles = 0;
            $hitBytes = 0;

            foreach ($disk->listContents($folder, true) as $item) {
                if (! $item->isFile()) {
                    continue;
                }

                $path = ltrim($item->path(), '/');
                $size = $this->sizeOf($disk, $item, $path);

                $files++;
                $bytes += $size;

                if (isset($referenced[$path])) {
                    $hitFiles++;
                    $hitBytes += $size;
                    $seen[$path] = true;
                } else {
                    $orphanList[] = [$path, $this->human($size)];
                }
            }

            $rows[] = [
                $folder,
                $files,
                $this->human($bytes),
                $hitFiles,
                $this->human($hitBytes),
                $files - $hitFiles,
                $this->human($bytes - $hitBytes),
            ];

            $totals['files'] += $files;
            $totals['bytes'] += $bytes;
            $totals['hitFiles'] += $hitFiles;
            $totals['hitBytes'] += $hitBytes;
        }

        $rows[] = [
            'TOTAL',
            $totals['files'],
            $this->human($totals['bytes']),
            $totals['hitFiles'],
            $this->human($totals['hitBytes']),
            $totals['files'] - $totals['hitFiles'],
            $this->human($totals['bytes'] - $totals['hitBytes']),
        ];

        $this->newLine();
        $this->table(
            ['folder', 'files', 'size', 'referenced', 'size', 'orphaned', 'size'],
            $rows
        );

        // References pointing at a file that is not there. These are already
        // broken links on the live site, whatever we decide about B2.
        $broken = [];
        foreach ($referenced as $path => $owners) {
            if (isset($seen[$path])) {
                continue;
            }
            if (! $this->inAuditedFolders($path, $folders)) {
                continue;
            }
            foreach ($owners as $owner) {
                $broken[] = [$path, $owner];
            }
        }

        if ($broken) {
            $this->newLine();
            $this->warn(count($broken) . ' database reference(s) point at a file that is NOT on the disk.');
            if ($this->option('broken')) {
                $this->table(['missing path', 'referenced by'], $broken);
            } else {
                $this->line('Run again with --broken to list them.');
            }
        }

        if ($orphanList) {
            $this->newLine();
            $this->warn(count($orphanList) . ' file(s) on the disk are referenced by nothing.');
            if ($this->option('orphans')) {
                $this->table(['orphaned file', 'size'], $orphanList);
            } else {
                $this->line('Run again with --orphans to list them.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Every path on this disk that something in the downloads hub points at,
     * mapped to a human description of what points at it.
     */
    private function referencedPaths(): array
    {
        $paths = [];

        foreach (Download::whereNotNull('external_url')->get(['id', 'name', 'external_url']) as $download) {
            $path = $this->pathFromUrl($download->external_url);
            if ($path !== null) {
                $paths[$path][] = "download #{$download->id} \"{$download->name}\"";
            }
        }

        foreach (DownloadFile::where('disk', self::DISK)->get(['id', 'download_id', 'path']) as $file) {
            $paths[ltrim($file->path, '/')][] = "download_file #{$file->id} (download #{$file->download_id})";
        }

        return $paths;
    }

    /**
     * Turn a stored URL into a path on this disk, or null when it points
     * somewhere else entirely (another host, a /storage/ path, a github
     * release). urldecode because names with spaces are stored escaped.
     */
    private function pathFromUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['path'])) {
            return null;
        }

        if (isset($parts['host']) && ! in_array($parts['host'], self::HOSTS, true)) {
            return null;
        }

        $path = urldecode($parts['path']);

        if (! str_starts_with($path, self::URL_PREFIX)) {
            return null;
        }

        return ltrim(substr($path, strlen(self::URL_PREFIX)), '/');
    }

    private function inAuditedFolders(string $path, array $folders): bool
    {
        foreach ($folders as $folder) {
            if (str_starts_with($path, trim($folder, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The SFTP listing usually carries the size already; fall back to a stat
     * only when it does not, so a big folder stays one round trip.
     */
    private function sizeOf($disk, $item, string $path): int
    {
        try {
            $size = $item->fileSize();
            if ($size !== null) {
                return (int) $size;
            }
        } catch (\Throwable) {
            // fall through to the explicit stat
        }

        try {
            return (int) $disk->size($path);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function human(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' kB';
        }

        return $bytes . ' B';
    }
}
