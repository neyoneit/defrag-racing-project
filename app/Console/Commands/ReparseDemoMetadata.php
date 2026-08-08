<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use Illuminate\Console\Command;
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
        {--apply : Write the corrections. Without this nothing is saved}';

    protected $description = 'Re-parse stored demos and correct metadata the old parser got wrong';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $ids = $this->option('id');
        $limit = (int) $this->option('limit');

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
        ];

        $query->orderBy('id')->chunkById(50, function ($demos) use ($apply, $limit, &$stats) {
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

                if (! $changes) {
                    $stats['unchanged']++;
                    continue;
                }

                $stats['changed']++;

                $this->line("  <fg=green>{$demo->id}</> {$demo->original_filename}");

                foreach ($changes as $column => [$was, $now]) {
                    $this->line("      {$column}: " . var_export($was, true) . ' -> ' . var_export($now, true));
                }

                // A demo already sitting on a record was matched under the wrong
                // name, so the match itself is in question. Reassigning it is an
                // admin's call, not this command's - it only says so.
                if ($demo->record_id) {
                    $stats['assigned']++;
                    $this->line('      <fg=yellow>already assigned to record ' . $demo->record_id . ' - assignment left alone, worth a look</>');
                }

                if ($apply) {
                    $demo->forceFill(array_map(fn ($pair) => $pair[1], $changes))->save();
                }

                if ($sleep = (float) $this->option('sleep')) {
                    usleep((int) ($sleep * 1_000_000));
                }
            }

            return true;
        });

        $this->newLine();
        $this->table(['', 'demos'], [
            ['read', $stats['read']],
            ['corrected', $stats['changed']],
            ['already right', $stats['unchanged']],
            ['file unreadable', $stats['unreadable']],
            ['parse failed', $stats['unparsed']],
            ['of the corrected, already assigned', $stats['assigned']],
        ]);

        if (! $apply && $stats['changed'] > 0) {
            $this->warn('Nothing was written. Re-run with --apply.');
        }

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
}
