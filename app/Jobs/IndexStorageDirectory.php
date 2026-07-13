<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Index one storage-VPS directory for the admin Storage Browser.
 *
 * Listing a huge SFTP directory (maps/ mirrors tens of thousands of pk3s)
 * takes longer than any HTTP request may run - clicking it 504'd through
 * Cloudflare. So the page never lists SFTP inline anymore: it shows
 * "Indexing..." and dispatches this job, which does the traversal without a
 * timeout, caches the result, and the page's poll picks it up.
 */
class IndexStorageDirectory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Huge dirs stream tens of thousands of READDIR entries over WAN. */
    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public string $disk,
        public string $path,
    ) {
    }

    public static function cacheKey(string $disk, string $path): string
    {
        return "storage-index:{$disk}:{$path}";
    }

    public static function pendingKey(string $disk, string $path): string
    {
        return "storage-index:pending:{$disk}:{$path}";
    }

    public function handle(): void
    {
        try {
            $listing = self::scan($this->disk, $this->path);
            // Listings change rarely (uploads go through the same panel and
            // invalidate explicitly), so cache generously - re-indexing maps/
            // on every visit is exactly what we're avoiding.
            Cache::put(self::cacheKey($this->disk, $this->path), $listing, now()->addHours(6));
        } catch (\Throwable $e) {
            // Short-lived error entry: the page surfaces it, and a retry is
            // possible right after instead of a stuck "Indexing..." state.
            Cache::put(self::cacheKey($this->disk, $this->path), [
                'dirs' => [],
                'files' => [],
                'error' => $e->getMessage(),
            ], 30);
        } finally {
            Cache::forget(self::pendingKey($this->disk, $this->path));
        }
    }

    /**
     * One listContents() pass (size + mtime ride along in the listing), plus
     * the symlink-to-directory fixup: symlinked dirs (bsp/, maps/) arrive
     * typed as FILES in SFTP listings - entries with no extension or unknown
     * size get one follow-the-link stat and become folders when they resolve
     * to a directory.
     */
    public static function scan(string $diskName, string $path): array
    {
        $disk = Storage::disk($diskName);
        $dirs = [];
        $files = [];

        foreach ($disk->getDriver()->listContents($path, false) as $item) {
            $name = basename($item->path());
            if ($item->isDir()) {
                $dirs[] = ['name' => $name, 'path' => $item->path(), 'type' => 'dir'];
            } else {
                $files[] = [
                    'name' => $name,
                    'path' => $item->path(),
                    'type' => 'file',
                    'size' => $item->fileSize(),
                    'mtime' => $item->lastModified(),
                ];
            }
        }

        foreach ($files as $i => $f) {
            $suspicious = $f['size'] === null || ! str_contains($f['name'], '.');
            if (! $suspicious) {
                continue;
            }
            try {
                if ($disk->directoryExists($f['path'])) {
                    $dirs[] = ['name' => $f['name'], 'path' => $f['path'], 'type' => 'dir'];
                    unset($files[$i]);
                }
            } catch (\Throwable) {
            }
        }

        $byName = fn ($a, $b) => strnatcasecmp($a['name'], $b['name']);
        usort($dirs, $byName);
        $files = array_values($files);
        usort($files, $byName);

        return ['dirs' => $dirs, 'files' => $files];
    }
}
