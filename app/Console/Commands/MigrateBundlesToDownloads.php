<?php

namespace App\Console\Commands;

use App\Models\Bundle;
use App\Models\BundleCategory;
use App\Models\Download;
use App\Models\DownloadCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Moves the pre-hub bundles/bundle_categories data into the downloads hub.
 *
 * The old categories are curated collections rather than asset types (a repack
 * holds maps, textures and shaders at once), so they are kept intact as
 * children of "Bundles and repacks" instead of being scattered across the
 * asset tree.
 *
 * Idempotent: every migrated row is tagged with a source_key, so a re-run
 * updates in place. The source tables are left untouched.
 */
class MigrateBundlesToDownloads extends Command
{
    protected $signature = 'downloads:migrate-bundles {--dry-run : Show what would happen without writing}';

    protected $description = 'Migrate the legacy Bundle/BundleCategory downloads into the community downloads hub';

    private const PARENT_SLUG = 'bundles-and-repacks';

    /**
     * Legacy rows the Family-Friendly article now owns. Migrating them too
     * would put the same pk3 in two places, which is exactly what the hub is
     * meant to stop. Matched on the old category name and on the file itself.
     */
    private const SUPERSEDED_CATEGORIES = ['Family-Friendly defrag'];
    private const SUPERSEDED_URLS = ['zzz-all_models-safe.pk3'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $parent = DownloadCategory::whereNull('parent_id')->where('slug', self::PARENT_SLUG)->first();

        if (! $parent) {
            $this->error('Root category "' . self::PARENT_SLUG . '" is missing. Run: db:seed --class=DownloadCategorySeeder');
            return 1;
        }

        $categories = BundleCategory::orderBy('position')->get();

        if ($categories->isEmpty()) {
            $this->info('No legacy bundle categories found. Nothing to do.');
            return 0;
        }

        $categoriesTouched = 0;
        $downloadsTouched = 0;
        $skipped = [];

        foreach ($categories as $old) {
            if (in_array($old->name, self::SUPERSEDED_CATEGORIES, true)) {
                $this->line("Skipping {$old->name}: the Family-Friendly article owns it now.");
                continue;
            }

            $slug = Str::slug($old->name);

            $this->line("Category: {$old->name} -> {$slug}");

            if ($dry) {
                $categoriesTouched++;
            } else {
                $category = DownloadCategory::updateOrCreate(
                    ['parent_id' => $parent->id, 'slug' => $slug],
                    ['name' => $old->name, 'position' => $old->position]
                );
                $categoriesTouched++;
            }

            $bundles = Bundle::where('category_id', $old->id)->orderBy('position')->get();

            foreach ($bundles as $bundle) {
                // Everything in the legacy table points at an external host;
                // `file` is a leftover column that was never populated. Handle
                // it anyway so a stray row cannot be silently dropped.
                $target = $bundle->url ?: ($bundle->file ? '/storage/' . $bundle->file : null);

                if (! $target) {
                    $skipped[] = "{$old->name} / {$bundle->name} (no url and no file)";
                    continue;
                }

                foreach (self::SUPERSEDED_URLS as $superseded) {
                    if (str_contains($target, $superseded)) {
                        $this->line("   - skipping {$bundle->name}: the Family-Friendly article owns it now.");
                        // 2 = the bundles loop, so only this file is skipped.
                        continue 2;
                    }
                }

                if (! $dry) {
                    Download::updateOrCreate(
                        ['source_key' => 'bundle:' . $bundle->id],
                        [
                            'user_id' => null,
                            'category_id' => $category->id,
                            'name' => $bundle->name,
                            'slug' => Str::slug($bundle->name),
                            'description' => $bundle->description,
                            'external_url' => $target,
                            'status' => Download::STATUS_PUBLISHED,
                            'is_locked' => false,
                            'position' => $bundle->position,
                        ]
                    );
                }

                $downloadsTouched++;
                $this->line("   - {$bundle->name}");
            }
        }

        foreach ($skipped as $s) {
            $this->warn("SKIPPED: {$s}");
        }

        $verb = $dry ? 'Would migrate' : 'Migrated';
        $this->info("{$verb} {$categoriesTouched} categories and {$downloadsTouched} downloads.");

        if (! empty($skipped)) {
            $this->warn(count($skipped) . ' entry/entries skipped (see above).');
        }

        return 0;
    }
}
