<?php

namespace App\Console\Commands;

use App\Jobs\RebuildDemosTopRanksJob;
use App\Models\OfflineRecord;
use App\Models\UploadedDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Re-reads stored demos with the current parser and corrects the metadata the
 * old one got wrong. Written for the demos that came out as a run by "defrag"
 * - old defrag prints its own name where the parser looked for the player's.
 *
 * It rewrites columns, never files. The archive in the bucket is untouched and
 * so is the demo's id, which everything else hangs off: an offline record, a
 * rendered video, community task votes, assignment reports, record flags.
 * Deleting the row and re-uploading would take all of that with it.
 *
 * Dry run unless --apply is passed.
 */
class ReparseDemoMetadata extends Command
{
    protected $signature = 'demos:reparse-metadata
        {--player=defrag : Only demos whose stored player_name is this}
        {--id=* : Specific demo ids, instead of the player filter}
        {--limit=0 : Stop after this many demos (0 = all)}
        {--sleep=0 : Seconds to wait between demos, to go easy on the bucket}
        {--apply : Write the corrections. Without this nothing is saved}
        {--journal= : Where to record what was written (default: storage/app/demo-reparse/<time>.jsonl)}
        {--revert= : Put back everything a journal file recorded, instead of parsing anything}';

    protected $description = 'Re-parse stored demos and correct metadata the old parser got wrong';

    public function handle(): int
    {
        if ($this->option('revert')) {
            return $this->revert($this->option('revert'));
        }

        $apply = (bool) $this->option('apply');
        $ids = $this->option('id');
        $limit = (int) $this->option('limit');
        $journal = $apply ? $this->openJournal() : null;

        $query = $ids
            ? UploadedDemo::whereIn('id', $ids)
            : UploadedDemo::where('player_name', $this->option('player'));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing matches.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Applying to ' : 'Dry run over ') . $total . ' demo(s).');

        $stats = [
            'read' => 0,
            'unreadable' => 0,
            'unparsed' => 0,
            'unchanged' => 0,
            'changed' => 0,
            'assigned' => 0,
            'physics_left' => 0,
        ];

        // Maps whose leaderboard was touched. The map page does not read these
        // columns live: MapsController::buildDemosTopReps caches the whole
        // Demos Top collection per map for an hour, keyed by a generation
        // counter. Correcting a name in SQL alone leaves the old name on the
        // page until that expires - it did exactly that on 2plyr and 24run.
        $touchedMaps = [];

        $query->orderBy('id')->chunkById(50, function ($demos) use ($apply, $limit, $journal, &$stats, &$touchedMaps) {
            foreach ($demos as $demo) {
                if ($limit > 0 && $stats['read'] >= $limit) {
                    return false;
                }

                $stats['read']++;

                $file = $this->readDemo($demo);

                if ($file === null) {
                    $stats['unreadable']++;
                    $this->line("  <fg=yellow>{$demo->id}</> file unreadable: {$demo->file_path}");
                    continue;
                }

                $meta = $this->parse($file);
                @unlink($file);

                if (! $meta || empty($meta['player_name'])) {
                    $stats['unparsed']++;
                    $this->line("  <fg=yellow>{$demo->id}</> could not be parsed");
                    continue;
                }

                $changes = $this->diff($demo, $meta);
                $offline = $this->offlineDiff($demo, $meta);

                if (! $changes && ! $offline) {
                    $stats['unchanged']++;
                    continue;
                }

                $stats['changed']++;

                $this->line("  <fg=green>{$demo->id}</> {$demo->original_filename}");

                foreach ($changes as $column => [$was, $now]) {
                    $this->line("      {$column}: " . var_export($was, true) . ' -> ' . var_export($now, true));
                }

                foreach ($offline['changes'] ?? [] as $column => [$was, $now]) {
                    $this->line("      offline record {$offline['id']} {$column}: "
                        . var_export($was, true) . ' -> ' . var_export($now, true));
                }

                // The offline record's physics decides which group it is ranked
                // in, so moving it would leave both groups holding stale ranks.
                // Say so and leave it; that one is a decision, not a typo.
                if (isset($changes['physics']) && $offline) {
                    $stats['physics_left']++;
                    $this->line('      <fg=yellow>offline record keeps physics ' . $offline['physics']
                        . ' - it is what the record is ranked under</>');
                }

                $this->line('      check: ' . url('/maps/' . $demo->map_name) . '  |  ' . url('/demos/' . $demo->id . '/download'));

                // A demo already sitting on a record was matched under the wrong
                // name, so the match itself is in question. Reassigning it is an
                // admin's call, not this command's - it only says so.
                if ($demo->record_id) {
                    $stats['assigned']++;
                    $this->line('      <fg=yellow>already assigned to record ' . $demo->record_id . ' - assignment left alone, worth a look</>');
                }

                if ($apply) {
                    // Journal first: a line written for a save that then fails
                    // reverts to the value already there, which is harmless.
                    // The other order can leave a change with no way back.
                    fwrite($journal, json_encode([
                        'id' => $demo->id,
                        'at' => now()->toIso8601String(),
                        'changes' => $changes,
                        'offline' => $offline ? ['id' => $offline['id'], 'changes' => $offline['changes']] : null,
                    ]) . "\n");

                    if ($changes) {
                        $demo->forceFill(array_map(fn ($pair) => $pair[1], $changes))->save();
                    }

                    if ($offline) {
                        OfflineRecord::where('id', $offline['id'])
                            ->update(array_map(fn ($pair) => $pair[1], $offline['changes']));
                    }

                    if ($demo->map_name) {
                        $touchedMaps[$demo->map_name] = true;
                    }
                }

                if ($sleep = (float) $this->option('sleep')) {
                    usleep((int) ($sleep * 1_000_000));
                }
            }

            return true;
        });

        $this->refreshMaps(array_keys($touchedMaps));

        $this->newLine();
        $this->table(['', 'demos'], [
            ['read', $stats['read']],
            ['corrected', $stats['changed']],
            ['already right', $stats['unchanged']],
            ['file unreadable', $stats['unreadable']],
            ['parse failed', $stats['unparsed']],
            ['of the corrected, already assigned', $stats['assigned']],
            ['offline record left on its old physics', $stats['physics_left']],
        ]);

        if (! $apply && $stats['changed'] > 0) {
            $this->warn('Nothing was written. Re-run with --apply.');
        }

        if ($journal) {
            fclose($journal);
            $this->info('Written down in: ' . $this->journalPath);
            $this->line('Undo this run with: php artisan demos:reparse-metadata --revert=' . $this->journalPath);
        }

        return self::SUCCESS;
    }

    /**
     * Makes the corrected names show up. Two things hold a stale copy: the
     * per-map Demos Top cache the map detail reads (bumping the generation
     * counter is enough, the next view recomputes) and the materialized
     * demos_top_ranks table the render queue reads, which the job rebuilds.
     * The job bumps the counter too, but it does so when a worker picks it
     * up - the page should not wait for the queue, so bump it here as well.
     */
    private function refreshMaps(array $maps): void
    {
        if (! $maps) {
            return;
        }

        foreach ($maps as $map) {
            Cache::increment('demostop_gen:' . $map);
            RebuildDemosTopRanksJob::dispatch($map);
        }

        $this->line('Refreshed the leaderboard of ' . count($maps) . ' map(s).');
    }

    private string $journalPath = '';

    /** @return resource */
    private function openJournal()
    {
        $this->journalPath = $this->option('journal')
            ?: storage_path('app/demo-reparse/' . now()->format('Y-m-d_His') . '.jsonl');

        @mkdir(dirname($this->journalPath), 0755, true);

        return fopen($this->journalPath, 'a');
    }

    /**
     * Puts back what a journal recorded. A column whose current value is not
     * what the run left there was changed by something else since - that one
     * is reported and skipped rather than overwritten.
     */
    private function revert(string $path): int
    {
        if (! is_readable($path)) {
            $this->error('No journal at ' . $path);

            return self::FAILURE;
        }

        $reverted = 0;
        $conflicts = 0;
        $missing = 0;
        $touchedMaps = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $entry = json_decode($line, true);

            if (! is_array($entry) || empty($entry['id'])) {
                continue;
            }

            $demo = UploadedDemo::find($entry['id']);

            if (! $demo) {
                $missing++;
                continue;
            }

            $restore = [];

            foreach ($entry['changes'] ?? [] as $column => [$was, $now]) {
                if ($demo->{$column} === $now) {
                    $restore[$column] = $was;
                    continue;
                }

                $conflicts++;
                $this->line("  <fg=yellow>{$demo->id}</> {$column} is now " . var_export($demo->{$column}, true)
                    . ', not ' . var_export($now, true) . ' - left alone');
            }

            if ($restore) {
                $demo->forceFill($restore)->save();
                $reverted++;
                $this->line("  <fg=green>{$demo->id}</> back to " . json_encode($restore));

                if ($demo->map_name) {
                    $touchedMaps[$demo->map_name] = true;
                }
            }

            if ($offline = ($entry['offline'] ?? null)) {
                $record = OfflineRecord::find($offline['id']);

                foreach ($offline['changes'] ?? [] as $column => [$was, $now]) {
                    if (! $record) {
                        $missing++;
                        break;
                    }

                    if ($record->{$column} !== $now) {
                        $conflicts++;
                        $this->line("  <fg=yellow>offline record {$record->id}</> {$column} is now "
                            . var_export($record->{$column}, true) . ' - left alone');
                        continue;
                    }

                    $record->forceFill([$column => $was])->save();
                    $this->line("  <fg=green>offline record {$record->id}</> {$column} back to " . var_export($was, true));

                    if ($record->map_name) {
                        $touchedMaps[$record->map_name] = true;
                    }
                }
            }
        }

        $this->refreshMaps(array_keys($touchedMaps));

        $this->newLine();
        $this->info("Reverted {$reverted} demo(s), {$conflicts} column(s) left alone, {$missing} demo(s) gone.");

        return self::SUCCESS;
    }

    /**
     * Pulls the demo out of storage into a temp file, unpacking the archive it
     * is normally stored in. Returns the path, or null if it cannot be read.
     */
    private function readDemo(UploadedDemo $demo): ?string
    {
        if (empty($demo->file_path)) {
            return null;
        }

        $local = str_starts_with($demo->file_path, 'demos/temp/')
            || str_starts_with($demo->file_path, 'demos/failed/');

        try {
            $contents = $local
                ? @file_get_contents(storage_path('app/' . $demo->file_path))
                : Storage::get($demo->file_path);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $contents) {
            return null;
        }

        $dir = sys_get_temp_dir() . '/demo_reparse_' . $demo->id;
        @mkdir($dir, 0755, true);

        // Decided on the bytes, not the name: a demo stored raw under a .7z
        // name would otherwise be read as a broken archive and skipped.
        if (! str_starts_with($contents, "7z\xBC\xAF\x27\x1C")) {
            $path = $dir . '/' . basename($demo->original_filename ?: 'demo.dm_68');
            file_put_contents($path, $contents);

            return $path;
        }

        $archive = $dir . '/archive.7z';
        file_put_contents($archive, $contents);

        exec(sprintf('7z x %s -o%s -y 2>&1', escapeshellarg($archive), escapeshellarg($dir)), $out, $code);
        @unlink($archive);

        if ($code !== 0) {
            return null;
        }

        $files = glob($dir . '/*.dm_*') ?: [];

        return $files ? reset($files) : null;
    }

    /** Runs the same parser the upload path runs. */
    private function parse(string $path): ?array
    {
        $script = app_path('Services/DemoProcessor/bin/process_single_demo.py');

        exec(sprintf('python3 %s %s --json 2>/dev/null', escapeshellarg($script), escapeshellarg($path)), $out);

        $json = json_decode(implode('', $out), true);

        return is_array($json) && empty($json['error']) ? $json : null;
    }

    /**
     * What the fresh parse disagrees with, as column => [stored, parsed].
     * Only the fields the misparse could have poisoned; nothing that an admin
     * or another person decided.
     */
    private function diff(UploadedDemo $demo, array $meta): array
    {
        $changes = [];

        if (! empty($meta['player_name']) && $meta['player_name'] !== $demo->player_name) {
            $changes['player_name'] = [$demo->player_name, $meta['player_name']];
        }

        if (! empty($meta['country']) && strtoupper($meta['country']) !== strtoupper((string) $demo->country)) {
            $changes['country'] = [$demo->country, strtoupper($meta['country'])];
        }

        // "df.vq3.tr" as the physics column writes it
        if (! empty($meta['physics'])) {
            $parts = explode('.', $meta['physics']);
            array_shift($parts);
            $physics = strtoupper(implode('.', $parts));

            if ($physics && $physics !== strtoupper((string) $demo->physics)) {
                $changes['physics'] = [$demo->physics, $physics];
            }
        }

        if (! empty($meta['suggested_filename'])) {
            $format = config('app.demo_compression_format', '7z');
            $suggested = pathinfo($meta['suggested_filename'], PATHINFO_FILENAME) . '.' . $format;

            // The name only; file_path stays as it is, so the bucket keeps the
            // object it already has and the mirror has nothing new to copy.
            if ($suggested !== $demo->processed_filename) {
                $changes['processed_filename'] = [$demo->processed_filename, $suggested];
            }
        }

        return $changes;
    }

    /**
     * The name lives twice: the demo carries it, and the offline record built
     * from it took a copy at the time. The map page shows the record's copy,
     * so fixing only the demo leaves the wrong name exactly where it was
     * reported. Returns null when there is nothing to do.
     */
    private function offlineDiff(UploadedDemo $demo, array $meta): ?array
    {
        $record = OfflineRecord::where('demo_id', $demo->id)->first();

        if (! $record || empty($meta['player_name'])) {
            return null;
        }

        if ($record->player_name === $meta['player_name']) {
            return null;
        }

        return [
            'id' => $record->id,
            'physics' => $record->physics,
            'changes' => ['player_name' => [$record->player_name, $meta['player_name']]],
        ];
    }
}
