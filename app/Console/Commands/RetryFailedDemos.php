<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Services\DemoProcessorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Puts the failed pile back through the pipeline.
 *
 * Most of that pile is not broken demos. The old parser read any two
 * dot-separated numbers in a filename as a run time, so a date like
 * "2003-07-01" reached get_time_span, which threw, and the exception took the
 * whole demo down. The stricter shape check makes those readable again, and
 * demos with no time of their own now have the Freestyle & Tricks section to
 * land in.
 *
 * The archive under demos/failed/<id>/ is never deleted, only read. A demo that
 * fails again is left exactly as it was.
 *
 * Reports without touching anything unless --apply is passed.
 */
class RetryFailedDemos extends Command
{
    protected $signature = 'demos:retry-failed
        {--id=* : Specific demo ids, instead of everything that failed}
        {--limit=0 : Stop after this many demos (0 = all)}
        {--apply : Actually put them back through. Without this nothing is written}
        {--queue : Hand each demo to the queue instead of processing it here}
        {--sleep=0 : Seconds to wait between demos}';

    protected $description = 'Retry demos the old parser could not read';

    public function handle(DemoProcessorService $processor): int
    {
        $apply = (bool) $this->option('apply');
        $ids = $this->option('id');
        $limit = (int) $this->option('limit');

        $query = $ids
            ? UploadedDemo::whereIn('id', $ids)
            : UploadedDemo::where('status', 'failed');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing matches.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Retrying ' : 'Dry run over ') . $total . ' demo(s)'
            . ($limit > 0 ? ', stopping after ' . $limit : '') . '.');

        $seen = 0;
        $readable = 0;
        $noSource = 0;
        $stillUnreadable = 0;
        $outcomes = [];

        $query->orderBy('id')->chunkById(200, function ($demos) use (
            &$seen, &$readable, &$noSource, &$stillUnreadable, &$outcomes, $apply, $limit, $processor
        ) {
            foreach ($demos as $demo) {
                if ($limit > 0 && $seen >= $limit) {
                    return false;
                }

                $seen++;

                $source = $this->locateSource($demo);

                if (! $source) {
                    $noSource++;
                    $this->line('  ' . $demo->id . '  no file on disk (' . $demo->file_path . ')');
                    continue;
                }

                $workDir = storage_path('app/demos/temp/' . $demo->id);
                $demoFile = $this->stage($source, $workDir, $demo->original_filename);

                if (! $demoFile) {
                    $noSource++;
                    $this->line('  ' . $demo->id . '  could not unpack ' . basename($source));
                    continue;
                }

                $metadata = $this->readMetadata($demoFile);

                if (! $metadata) {
                    $stillUnreadable++;
                    $this->cleanup($workDir);
                    continue;
                }

                $readable++;

                if (! $apply) {
                    $this->line(sprintf(
                        '  %-8s %-22s %-18s %s',
                        $demo->id,
                        $metadata['map_name'] ?? '?',
                        $metadata['player_name'] ?? '?',
                        $metadata['time_seconds'] ? $metadata['time_seconds'] . 's' : 'no time'
                    ));
                    $this->cleanup($workDir);
                    continue;
                }

                $outcome = $this->retry($demo, $processor, $source);
                $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;
                $this->line('  ' . $demo->id . '  -> ' . $outcome);

                if ($sleep = (int) $this->option('sleep')) {
                    sleep($sleep);
                }
            }

            return true;
        });

        $this->newLine();
        $this->info('Looked at ' . $seen . ' demo(s).');
        $this->line('  readable with the current parser: ' . $readable);
        $this->line('  still unreadable: ' . $stillUnreadable);
        $this->line('  no file to read: ' . $noSource);

        foreach ($outcomes as $status => $count) {
            $this->line('  ended as ' . $status . ': ' . $count);
        }

        if (! $apply && $readable > 0) {
            $this->newLine();
            $this->comment('Nothing was written. Add --apply to put these back through.');
        }

        return self::SUCCESS;
    }

    /**
     * The file to read. A failed demo normally sits compressed under
     * demos/failed/<id>/, but older ones were left uncompressed and a few still
     * point at wherever they were when they failed.
     */
    private function locateSource(UploadedDemo $demo): ?string
    {
        $failedDir = storage_path('app/demos/failed/' . $demo->id);

        $candidates = [
            $failedDir . '/' . $demo->original_filename,
            storage_path('app/' . $demo->file_path),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        foreach (glob($failedDir . '/*') ?: [] as $found) {
            if (is_file($found)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Put an uncompressed copy in the work directory and hand back its path.
     */
    private function stage(string $source, string $workDir, string $originalFilename): ?string
    {
        $this->cleanup($workDir);

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            return null;
        }

        $target = $workDir . '/' . $originalFilename;

        if (! str_ends_with(strtolower($source), '.7z')) {
            return copy($source, $target) ? $target : null;
        }

        $process = new Process(['7z', 'e', '-y', '-o' . $workDir, $source]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $extracted = glob($workDir . '/*.dm_*') ?: [];

        if (! $extracted) {
            return null;
        }

        if ($extracted[0] !== $target) {
            rename($extracted[0], $target);
        }

        return $target;
    }

    /**
     * What the current parser makes of the file, or null if it still cannot
     * read it.
     */
    private function readMetadata(string $demoFile): ?array
    {
        $script = base_path('app/Services/DemoProcessor/bin/process_single_demo.py');

        $process = new Process(['python3', $script, $demoFile, '--json']);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $decoded = json_decode(trim($process->getOutput()), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Reset the row the way the per-demo reprocess button does, then run it.
     */
    private function retry(UploadedDemo $demo, DemoProcessorService $processor, string $source): string
    {
        // Where the file was before we pointed the row at a temp copy. The
        // pipeline rejects a non-defrag demo by deleting that temp directory
        // without moving anything, which would leave the row pointing at a path
        // that no longer exists while the archive sits untouched where it was.
        // The file we actually read is the fallback, since a row can arrive
        // already pointing at nothing.
        // A row can also arrive already pointing into demos/temp from an
        // earlier retry, which is never somewhere to fall back to.
        $stored = (string) $demo->file_path;
        $usable = $stored !== ''
            && ! str_starts_with($stored, 'demos/temp/')
            && is_file(storage_path('app/' . $stored));

        $originalPath = $usable
            ? $stored
            : ltrim(str_replace(storage_path('app'), '', $source), '/');

        $demo->update([
            'status' => 'uploaded',
            'file_path' => 'demos/temp/' . $demo->id . '/' . $demo->original_filename,
            'processed_filename' => null,
            'map_name' => null,
            'physics' => null,
            'gametype' => null,
            'player_name' => null,
            'q3df_login_name' => null,
            'q3df_login_name_colored' => null,
            'time_ms' => null,
            'processing_output' => null,
            'record_id' => null,
            'match_method' => null,
        ]);

        if ($this->option('queue')) {
            \App\Jobs\ProcessDemoJob::dispatch($demo);

            return 'queued';
        }

        try {
            $processor->processDemo($demo);
        } catch (\Throwable $e) {
            Log::error('Retry of a failed demo threw', ['demo_id' => $demo->id, 'error' => $e->getMessage()]);

            $demo->update([
                'status' => 'failed',
                'file_path' => $originalPath,
                'processing_output' => '[' . now()->format('Y-m-d H:i:s') . '] Retry failed: ' . $e->getMessage(),
            ]);

            return 'threw: ' . substr($e->getMessage(), 0, 60);
        }

        $demo = $demo->fresh();

        if ($demo->status === 'failed' && $demo->file_path !== $originalPath
            && ! is_file(storage_path('app/' . $demo->file_path))) {
            $demo->update(['file_path' => $originalPath]);
        }

        return (string) $demo->status;
    }

    private function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($dir);
    }
}
