<?php

namespace App\Console\Commands;

use App\Models\Download;
use App\Models\DownloadCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Publishes the family-friendly pk3 set into its locked category, which
 * renders as a self-updating article rather than a listing.
 *
 * The files are hosted on our own download hosts and change rarely, so there
 * is no listing to scrape: the set is declared here and a HEAD request keeps
 * each entry's size and date honest.
 *
 * Idempotent via source_key.
 */
class SyncFamilyFriendly extends Command
{
    protected $signature = 'downloads:sync-family-friendly';

    protected $description = 'Refresh the Family-Friendly defrag pk3 set in the downloads hub';

    private const FILES = [
        [
            'key' => 'safe-textures',
            'name' => 'Safe textures',
            'url' => 'https://bundles.defrag.racing/zzzzz-safe-textures.pk3',
            'description' => 'Replaces the adult and gore artwork on world surfaces with clean versions. The zzzzz- prefix makes it load last, so it overrides everything else.',
        ],
        [
            'key' => 'safe-models',
            'name' => 'Safe player models',
            'url' => 'https://dl.defrag.racing/downloads/useful-pk3s/zzz-all_models-safe.pk3',
            'description' => 'Every player model in one pack, with the revealing and gory skins swapped for safe ones. Large, but it covers whoever you run into on a server.',
        ],
    ];

    public function handle(): int
    {
        $category = DownloadCategory::where('auto_source', DownloadCategory::SOURCE_FAMILY_FRIENDLY)->first();

        if (! $category) {
            $this->error('The Family-Friendly category is missing. Run: db:seed --class=DownloadCategorySeeder');
            return 1;
        }

        $seen = [];

        foreach (self::FILES as $position => $file) {
            $head = $this->head($file['url']);

            if (! $head) {
                // Keep whatever is already published rather than blanking the
                // article because a host blipped.
                $this->warn("Unreachable, leaving as is: {$file['url']}");
                $existing = Download::where('source_key', 'family_friendly:' . $file['key'])->first();

                if ($existing) {
                    $seen[] = $existing->source_key;
                }

                continue;
            }

            $key = 'family_friendly:' . $file['key'];
            $seen[] = $key;

            Download::updateOrCreate(
                ['source_key' => $key],
                [
                    'user_id' => null,
                    'category_id' => $category->id,
                    'name' => $file['name'],
                    'slug' => $file['key'],
                    'description' => $file['description'],
                    'external_url' => $file['url'],
                    'is_locked' => true,
                    'status' => Download::STATUS_PUBLISHED,
                    'position' => $position,
                    'meta' => [
                        'filename' => basename(parse_url($file['url'], PHP_URL_PATH)),
                        'size' => $head['size'],
                        'updated_at' => $head['last_modified'],
                    ],
                ]
            );

            $this->line("  {$file['name']} - " . $this->human($head['size']));
        }

        $removed = Download::where('category_id', $category->id)
            ->where('source_key', 'like', 'family_friendly:%')
            ->whereNotIn('source_key', $seen)
            ->delete();

        $this->info(sprintf(
            'Published %d file(s)%s.',
            count($seen),
            $removed ? ", removed {$removed} stale" : ''
        ));

        return 0;
    }

    /**
     * @return array{size: int, last_modified: ?string}|null
     */
    private function head(string $url): ?array
    {
        try {
            $response = Http::withOptions(['verify' => false])->timeout(20)->head($url);

            if (! $response->successful()) {
                return null;
            }

            $modified = $response->header('Last-Modified');

            return [
                'size' => (int) $response->header('Content-Length'),
                'last_modified' => $modified ? Carbon::parse($modified)->toIso8601String() : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('SyncFamilyFriendly: HEAD failed for ' . $url . ' - ' . $e->getMessage());

            return null;
        }
    }

    private function human(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1024) . ' KB';
    }
}
