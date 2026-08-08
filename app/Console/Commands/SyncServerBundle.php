<?php

namespace App\Console\Commands;

use App\Models\Download;
use App\Models\DownloadCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Publishes the server bundle archives into the locked "DeFRaG server bundle"
 * category, which renders as a self-updating article rather than a listing.
 *
 * Reads what bundle:build-server produced on the dl_storage disk: the two
 * archives plus the dfsv-core.version.json marker recording the engine build
 * and mod that went into them.
 *
 * Idempotent via source_key.
 */
class SyncServerBundle extends Command
{
    protected $signature = 'downloads:sync-server-bundle';

    protected $description = 'Publish the DeFRaG server bundle archives into the downloads hub';

    private const DISK = 'dl_storage';
    private const PUBLIC_BASE = 'https://dl.defrag.racing/downloads/';

    /**
     * Each core archive carries its own version marker, because Linux and
     * Windows are built from separate static inputs and can be a release
     * apart. `platform` is what splits the article in two; baseq3 is `both`,
     * being the same 485 MB of id paks either way.
     */
    private const ARCHIVES = [
        [
            'file' => 'dfsv-core.tar',
            'name' => 'Server core (Linux)',
            'platform' => 'linux',
            'marker' => 'dfsv-core.version.json',
            'description' => 'The oDFe dedicated engine, the DeFRaG mod, the record system modules and ip4db. Rebuilt automatically whenever a new engine build or mod release appears.',
        ],
        [
            'file' => 'dfsv-core-win.zip',
            'name' => 'Server core (Windows)',
            'platform' => 'windows',
            'marker' => 'dfsv-core-win.version.json',
            'description' => 'The same engine, mod and record system modules built for Windows, plus the support DLLs. Rebuilt on the same schedule as the Linux core.',
        ],
        [
            'file' => 'dfsv-baseq3.tar',
            'name' => 'baseq3 assets',
            'platform' => 'both',
            'marker' => null,
            'description' => 'The stock Quake III Arena paks (pak0 to pak8). Never changes, so the installer only pulls it when baseq3/pak0.pk3 is missing.',
        ],
    ];

    public function handle(): int
    {
        $category = DownloadCategory::where('auto_source', DownloadCategory::SOURCE_SERVER_BUNDLE)->first();

        if (! $category) {
            $this->error('The server bundle category is missing. Run: db:seed --class=DownloadCategorySeeder');
            return 1;
        }

        $disk = Storage::disk(self::DISK);
        $markers = [];
        $seen = [];

        foreach (self::ARCHIVES as $position => $archive) {
            // Prefer the disk (authoritative right after a build), but fall
            // back to a HEAD request on the public URL: dfsv-baseq3.tar only
            // exists on the prod disk, and the article should be complete no
            // matter which host runs the sync.
            if ($disk->exists($archive['file'])) {
                $size = $disk->size($archive['file']);
            } else {
                $size = $this->remoteSize(self::PUBLIC_BASE . $archive['file']);

                if ($size === null) {
                    $this->warn("Not on the disk and unreachable at the public URL, skipping: {$archive['file']}");
                    continue;
                }
            }

            $key = 'server_bundle:' . $archive['file'];
            $seen[] = $key;

            $marker = [];

            if ($archive['marker']) {
                if ($disk->exists($archive['marker'])) {
                    $marker = json_decode($disk->get($archive['marker']), true) ?: [];
                    $markers[$archive['platform']] = $marker;
                } else {
                    $this->warn("{$archive['marker']} not found. Run bundle:build-server first; publishing the size only.");
                }
            }

            Download::updateOrCreate(
                ['source_key' => $key],
                [
                    'user_id' => null,
                    'category_id' => $category->id,
                    'name' => $archive['name'],
                    'slug' => str_replace('.', '-', $archive['file']),
                    'description' => $archive['description'],
                    'external_url' => self::PUBLIC_BASE . $archive['file'],
                    'is_locked' => true,
                    'status' => Download::STATUS_PUBLISHED,
                    'position' => $position,
                    'meta' => [
                        'filename' => $archive['file'],
                        'platform' => $archive['platform'],
                        'size' => $size,
                        'built_at' => $marker['built_at'] ?? null,
                        'engine' => $marker['engine'] ?? null,
                        'mod' => $marker['mod'] ?? null,
                    ],
                ]
            );

            $this->line("  {$archive['file']} - " . $this->human($size));
        }

        if (empty($seen)) {
            $this->error('No archive is on the disk or reachable. Nothing published.');
            return 1;
        }

        $removed = Download::where('category_id', $category->id)
            ->where('source_key', 'like', 'server_bundle:%')
            ->whereNotIn('source_key', $seen)
            ->delete();

        $this->info(sprintf(
            'Published %d archive(s)%s. Linux mod %s, Windows mod %s.',
            count($seen),
            $removed ? ", removed {$removed} stale" : '',
            $markers['linux']['mod'] ?? 'unknown',
            $markers['windows']['mod'] ?? 'unknown'
        ));

        return 0;
    }

    private function remoteSize(string $url): ?int
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
                ->timeout(20)
                ->head($url);

            if (! $response->successful()) {
                return null;
            }

            return (int) $response->header('Content-Length') ?: null;
        } catch (\Throwable $e) {
            return null;
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

        return round($bytes / 1024) . ' KB';
    }
}
