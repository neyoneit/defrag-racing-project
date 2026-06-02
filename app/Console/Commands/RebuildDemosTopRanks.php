<?php

namespace App\Console\Commands;

use App\Models\Record;
use App\Models\OfflineRecord;
use App\Models\UploadedDemo;
use App\Services\DemosTopRankService;
use App\Services\DemoProfileResolver;
use Illuminate\Console\Command;

/**
 * (Re)build the materialized demos_top_ranks table.
 *
 * Run once as a full backfill before the auto render queue starts trusting
 * the table, then nightly as a safety net against any missed incremental
 * invalidation. Pass --map=name to rebuild a single map.
 */
class RebuildDemosTopRanks extends Command
{
    protected $signature = 'demos:rebuild-top-ranks {--map= : Rebuild only this map}';

    protected $description = 'Rebuild the materialized Demos Top ranking (demos_top_ranks)';

    public function handle(DemosTopRankService $service): int
    {
        // One shared resolver for the whole run: the global user/alias buckets
        // load once instead of per map (the difference between minutes and hours
        // across the full map set).
        $resolver = new DemoProfileResolver();

        if ($map = $this->option('map')) {
            $rows = $service->rebuildMap($map, $resolver);
            $this->info("Rebuilt {$map}: {$rows} rows.");
            return self::SUCCESS;
        }

        // Every map that has any ranked entity: main records, offline records,
        // or assigned online demos.
        $maps = collect()
            ->merge(Record::distinct()->pluck('mapname'))
            ->merge(OfflineRecord::distinct()->pluck('map_name'))
            ->merge(UploadedDemo::where('status', 'assigned')->whereNotNull('map_name')->distinct()->pluck('map_name'))
            ->filter()
            ->unique()
            ->values();

        $this->info("Rebuilding Demos Top ranks for {$maps->count()} maps...");
        $bar = $this->output->createProgressBar($maps->count());
        $bar->start();

        $totalRows = 0;
        $failed = [];
        foreach ($maps as $map) {
            try {
                $totalRows += $service->rebuildMap($map, $resolver);
            } catch (\Throwable $e) {
                $failed[] = $map;
                $this->newLine();
                $this->warn("  {$map}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. {$totalRows} rows across {$maps->count()} maps." . (count($failed) ? ' Failed: ' . implode(', ', $failed) : ''));

        return self::SUCCESS;
    }
}
