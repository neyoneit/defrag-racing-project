<?php

namespace App\Console\Commands;

use App\Models\Download;
use App\Models\DownloadCategory;
use App\Services\DefragReleaseScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mirrors the q3defrag.org release listing into the locked "DeFRaG mod"
 * category, which renders as a self-updating article rather than a listing.
 *
 * The files stay on q3defrag.org: each entry only carries an external_url plus
 * the version, date, size and channel needed to draw the release tables.
 *
 * Idempotent via source_key ('defrag_mod:stable:1.91').
 */
class SyncDefragMod extends Command
{
    protected $signature = 'downloads:sync-defrag-mod';

    protected $description = 'Mirror the DeFRaG mod releases from q3defrag.org into the downloads hub';

    public function handle(DefragReleaseScraper $scraper): int
    {
        $category = DownloadCategory::where('auto_source', DownloadCategory::SOURCE_DEFRAG_MOD)->first();

        if (! $category) {
            $this->error('The DeFRaG mod category is missing. Run: db:seed --class=DownloadCategorySeeder');
            return 1;
        }

        try {
            $stable = $scraper->stable();
            $beta = $scraper->beta();
        } catch (\Throwable $e) {
            $this->error('Failed to fetch: ' . $e->getMessage());
            Log::warning('SyncDefragMod: fetch failed - ' . $e->getMessage());
            return 1;
        }

        if (empty($stable)) {
            $this->warn('No stable releases found. q3defrag.org may be down - leaving existing entries alone.');
            return 1;
        }

        $seen = [];
        $latestVersion = end($stable)['version'];

        foreach ([['stable', $stable], ['beta', $beta]] as [$channel, $files]) {
            foreach ($files as $file) {
                $key = "defrag_mod:{$channel}:{$file['version']}";
                $seen[] = $key;

                Download::updateOrCreate(
                    ['source_key' => $key],
                    [
                        'user_id' => null,
                        'category_id' => $category->id,
                        'name' => "DeFRaG {$file['version']}" . ($channel === 'beta' ? ' beta' : ''),
                        'slug' => Str::slug("defrag-{$file['version']}-{$channel}"),
                        'description' => null,
                        'external_url' => $file['url'],
                        'is_locked' => true,
                        'status' => Download::STATUS_PUBLISHED,
                        'defrag_only' => true,
                        'meta' => [
                            'channel' => $channel,
                            'version' => $file['version'],
                            'date' => $file['date'],
                            'size' => (int) $file['size'],
                            'filename' => $file['filename'],
                            'is_latest' => $channel === 'stable' && $file['version'] === $latestVersion,
                        ],
                    ]
                );
            }
        }

        // A release pulled from q3defrag.org should not linger here.
        $removed = Download::where('category_id', $category->id)
            ->where('source_key', 'like', 'defrag_mod:%')
            ->whereNotIn('source_key', $seen)
            ->delete();

        $this->info(sprintf(
            'Synced %d stable + %d beta releases (latest %s)%s.',
            count($stable),
            count($beta),
            $latestVersion,
            $removed ? ", removed {$removed} stale" : ''
        ));

        return 0;
    }
}
