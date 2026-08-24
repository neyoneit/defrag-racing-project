<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * How many demos carry the wrong physics, and which.
 *
 * The parser used to take the physics from `df_promode` in serverinfo. That
 * cvar says what the server is set to, not what is being played, and on a
 * mixed server the two come apart every time somebody votes the physics
 * across. The physics now comes from the movement in the snapshots instead.
 *
 * This changes nothing. It is here to size the damage before anything is
 * rewritten, because the stored physics is what a record, a comps entry and a
 * demo's own page all read.
 *
 * Two ways of asking, and they answer different questions:
 *
 *   default     compare the stored physics against the tag in the demo's own
 *               filename. Free - no file is read - and it covers the whole
 *               database, but it only sees demos whose uploader kept the name
 *               DemoCleaner3 or the mod gave them.
 *
 *   --reparse   read the file again with the fixed parser. The real answer,
 *               and the only one for a demo with no tag in its name, but every
 *               file has to come down from storage first.
 */
class CheckDemoPhysics extends Command
{
    protected $signature = 'demos:check-physics
                            {--comps : only demos entered into comps}
                            {--reparse : read each file again instead of trusting its name}
                            {--limit=0 : stop after this many demos}
                            {--show=20 : how many mismatches to print}';

    protected $description = 'Report demos whose stored physics disagrees with the demo itself. Changes nothing.';

    public function handle(): int
    {
        $query = UploadedDemo::query()
            ->whereNotNull('physics')
            ->where('physics', '!=', '')
            ->select('id', 'original_filename', 'physics', 'file_path')
            ->orderBy('id');

        if ($this->option('comps')) {
            $query->whereIn('id', function ($q) {
                $q->from('comp_submissions')->select('uploaded_demo_id')->whereNotNull('uploaded_demo_id');
            });
        }

        // Not ->limit(): chunk() writes its own limit and offset onto the
        // query and would quietly ignore it. Counted in the loop instead.
        $limit = (int) $this->option('limit');

        $reparse = (bool) $this->option('reparse');
        $show = (int) $this->option('show');

        $checked = 0;
        $readable = 0;
        $bad = [];
        $failed = 0;

        $this->info($reparse
            ? 'Reading every file again. This is slow.'
            : 'Comparing against the filename. No file is read.');

        $query->chunk($reparse ? 200 : 20000, function ($rows) use (&$checked, &$readable, &$bad, &$failed, $reparse, $limit) {
            foreach ($rows as $demo) {
                if ($limit > 0 && $checked >= $limit) {
                    return false;
                }

                $checked++;

                $truth = $reparse ? $this->fromFile($demo, $failed) : $this->fromName($demo);

                if ($truth === null) {
                    continue;
                }

                $readable++;
                $stored = $this->plain($demo->physics);

                if ($stored !== $truth) {
                    $bad[] = [$demo->id, $stored, $truth, (string) $demo->original_filename];
                }
            }
        });

        $this->newLine();
        $this->line('checked:     ' . $checked);
        $this->line($reparse ? 'read back:   ' . $readable : 'named:       ' . $readable);
        $this->line('mismatched:  ' . count($bad));

        if ($reparse && $failed > 0) {
            $this->warn('could not read: ' . $failed);
        }

        if ($bad) {
            $this->newLine();
            $this->table(
                ['demo', 'stored', 'really', 'filename'],
                array_map(
                    fn ($r) => [$r[0], $r[1], $r[2], mb_substr($r[3], 0, 60)],
                    array_slice($bad, 0, $show)
                )
            );

            if (count($bad) > $show) {
                $this->line('... and ' . (count($bad) - $show) . ' more. Raise --show to see them.');
            }
        }

        return self::SUCCESS;
    }

    /** CPM or VQ3 out of a stored value like `CPM.TR`. */
    private function plain(?string $physics): ?string
    {
        $first = strtoupper(explode('.', (string) $physics)[0]);

        return in_array($first, ['CPM', 'VQ3'], true) ? $first : null;
    }

    /** The physics in the demo's own name, as DemoCleaner3 or the mod wrote it. */
    private function fromName(UploadedDemo $demo): ?string
    {
        return preg_match('/\[[a-z]+\.(cpm|vq3)\]/i', (string) $demo->original_filename, $m)
            ? strtoupper($m[1])
            : null;
    }

    /** The physics the parser reads out of the file itself. */
    private function fromFile(UploadedDemo $demo, int &$failed): ?string
    {
        $tmp = null;

        try {
            if (! $demo->file_path || ! Storage::exists($demo->file_path)) {
                $failed++;

                return null;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'phys') . '.dm_68';
            file_put_contents($tmp, Storage::get($demo->file_path));

            $process = new Process([
                'python3',
                app_path('Services/DemoProcessor/bin/process_single_demo.py'),
                $tmp,
                '--json',
            ]);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                $failed++;

                return null;
            }

            $meta = json_decode($process->getOutput(), true);

            // `mdf.cpm`, `df.vq3.tr` - the gameplay physics is the second part.
            $parts = explode('.', strtoupper((string) ($meta['physics'] ?? '')));

            return in_array($parts[1] ?? '', ['CPM', 'VQ3'], true) ? $parts[1] : null;
        } catch (\Throwable $e) {
            $failed++;

            return null;
        } finally {
            if ($tmp && file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
