<?php

namespace App\Console\Commands;

use App\Jobs\RebuildDemosTopRanksJob;
use App\Models\OfflineRecord;
use Illuminate\Console\Command;

/**
 * Removes the run times that were never run.
 *
 * A demo that carries no time of its own has one read out of its filename, and
 * the record says so - validity_flag `client_finish=false` means the time did
 * not come from the run. The old rule took any two dot-separated numbers, so a
 * speed in "run_vq3cj_607.1_scripted" became a ten-minute run and stood at the
 * top of a map with 1617 demos, and "0.7" became a world record of seven
 * milliseconds.
 *
 * The parser only accepts defrag's own shape now (`cd31fba`), but the rows it
 * already wrote still hold those times, and five of them still stand in
 * leaderboards. This re-reads every such record against the strict rule and
 * drops the ones whose time nothing in the original filename can account for.
 *
 * The demo, its file, its player and its map all stay. Only the ranked time
 * goes, and with no time the demo shows up under the map's Freestyle & Tricks
 * instead - which is what it always was. Ranks are rebuilt for the maps
 * touched, so the leaderboard behind them closes up straight away.
 *
 * Reports without writing unless --apply.
 */
class DropInventedTimes extends Command
{
    protected $signature = 'demos:drop-invented-times
        {--apply : Actually drop them. Without this nothing is written}';

    protected $description = 'Drop times read out of a filename that never held one';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $records = OfflineRecord::with('demo')
            ->where('validity_flag', 'like', '%client_finish=false%')
            ->get();

        $invented = $records->filter(function ($record) {
            $name = (string) ($record->demo?->original_filename ?? '');

            return $name !== '' && ! $this->carriesATime($name);
        });

        $this->info($records->count().' record(s) took their time from a filename, '
            .$invented->count().' of them from something that is not a time:');
        $this->newLine();

        foreach ($invented as $record) {
            $this->line('  demo '.str_pad((string) $record->demo_id, 8)
                .str_pad((string) $record->map_name, 20)
                .str_pad((string) $record->physics, 8)
                .'reads as '.str_pad($this->asTime((int) $record->time_ms), 12)
                .'rank '.$record->rank);
            $this->line('      '.$record->demo->original_filename);
        }

        if (! $invented->count()) {
            return self::SUCCESS;
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Nothing was written. Add --apply to drop these.');

            return self::SUCCESS;
        }

        $maps = [];

        foreach ($invented as $record) {
            $maps[(string) $record->map_name] = true;

            // The demo keeps everything else - its file, its player, its map.
            // Only the time goes, because there never was one.
            $record->demo->update(['time_ms' => null]);
            $record->delete();
        }

        $this->newLine();
        $this->info('Dropped '.$invented->count().' record(s). Rebuilding '.count($maps).' map(s):');

        foreach (array_keys($maps) as $map) {
            RebuildDemosTopRanksJob::dispatchSync($map);
            $this->line('  '.$map);
        }

        return self::SUCCESS;
    }

    /**
     * Whether the name holds a time defrag itself could have written: MM.SS.mmm,
     * minutes two or three digits, seconds two, milliseconds three, dot or dash
     * between. Exactly the shape the parser accepts now, so this asks what it
     * would make of the name today - and a name it can find no time in is a name
     * the old loose rule invented one from.
     *
     * Deliberately not compared to the stored value: a handful of these sit one
     * millisecond off their filename, which says the time came from somewhere
     * else, not that anybody made it up.
     */
    private function carriesATime(string $name): bool
    {
        return (bool) preg_match('/(?<![0-9])[0-9]{2,3}[-.][0-9]{2}[-.][0-9]{3}(?![0-9])/', $name);
    }

    private function asTime(int $ms): string
    {
        return sprintf('%02d.%02d.%03d', intdiv($ms, 60000), intdiv($ms % 60000, 1000), $ms % 1000);
    }
}
