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
    private const MARKER = 'dfsv-core.version.json';
    private const PUBLIC_BASE = 'https://dl.defrag.racing/downloads/';

    private const ARCHIVES = [
        [
            'file' => 'dfsv-core.tar',
            'name' => 'Server core',
            'description' => 'The oDFe dedicated engine, the DeFRaG mod, the record system modules and ip4db. Rebuilt automatically whenever a new engine build or mod release appears.',
        ],
        [
            'file' => 'dfsv-baseq3.tar',
            'name' => 'baseq3 assets',
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
        $marker = [];

        if ($disk->exists(self::MARKER)) {
            $marker = json_decode($disk->get(self::MARKER), true) ?: [];
        } else {
            $this->warn(self::MARKER . ' not found. Run bundle:build-server first; publishing sizes only.');
        }

        $seen = [];

        foreach (self::ARCHIVES as $position => $archive) {
            if (! $disk->exists($archive['file'])) {
                $this->warn("Missing on {$disk->getConfig()['driver']} disk, skipping: {$archive['file']}");
                continue;
            }

            $key = 'server_bundle:' . $archive['file'];
            $seen[] = $key;

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
                        'size' => $disk->size($archive['file']),
                        'built_at' => $marker['built_at'] ?? null,
                        'engine' => $marker['engine'] ?? null,
                        'mod' => $marker['mod'] ?? null,
                    ],
                ]
            );

            $this->line("  {$archive['file']} - " . $this->human($disk->size($archive['file'])));
        }

        if (empty($seen)) {
            $this->error('Neither archive is on the disk. Nothing published.');
            return 1;
        }

        $removed = Download::where('category_id', $category->id)
            ->where('source_key', 'like', 'server_bundle:%')
            ->whereNotIn('source_key', $seen)
            ->delete();

        $this->info(sprintf(
            'Published %d archive(s)%s. Engine %s, mod %s.',
            count($seen),
            $removed ? ", removed {$removed} stale" : '',
            $marker['engine'] ?? 'unknown',
            $marker['mod'] ?? 'unknown'
        ));

        return 0;
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
