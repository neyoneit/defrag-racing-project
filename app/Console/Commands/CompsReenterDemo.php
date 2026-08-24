<?php

namespace App\Console\Commands;

use App\Models\CompSubmission;
use App\Models\UploadedDemo;
use App\Services\Comps\UploadGuard;
use Illuminate\Console\Command;

/**
 * Put a demo through the comps guard again, entry and all.
 *
 * Written for the day the parser's physics was corrected underneath demos
 * that had already been entered. Correcting the demo is not enough: the entry
 * carries its own physics, decided when it was made, and nothing re-reads it.
 * Two runs sat in the wrong half of a round for a week that way, one of them
 * beating times it was never actually racing.
 *
 * The guard itself refuses to touch a demo that already has an entry, and
 * rightly so - that is what stops a re-parse quietly rewriting the standings.
 * So the entry is taken away first and the guard is asked to decide again from
 * scratch, against the map, the physics and the window like any other run.
 *
 * Never a finished round. Those standings are frozen, people have read them,
 * and a prize may already have been settled on them. If a finished round is
 * wrong it is a decision for a person, not for a command.
 *
 * Dry run unless --apply is passed.
 */
class CompsReenterDemo extends Command
{
    protected $signature = 'comps:reenter
        {--id=* : Demo ids to put through the guard again}
        {--apply : Write the change. Without this nothing is saved}';

    protected $description = 'Re-enter a demo into comps after its physics was corrected';

    public function handle(UploadGuard $guard): int
    {
        $ids = $this->option('id');

        if (! $ids) {
            $this->error('Give it at least one --id.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        // withUnreleasedComps(), or a demo the round is holding - which is
        // every demo this command is for - is invisible.
        $demos = UploadedDemo::withUnreleasedComps()->whereIn('id', $ids)->get();

        if ($demos->isEmpty()) {
            $this->error('None of those demos exist.');

            return self::FAILURE;
        }

        // Said out loud rather than left to be noticed in the count. A typo in
        // an id would otherwise look exactly like a demo that needed nothing.
        $missing = array_diff(array_map('intval', $ids), $demos->pluck('id')->all());

        if ($missing) {
            $this->warn('No such demo: ' . implode(', ', $missing));
        }

        $this->info(($apply ? 'Applying to ' : 'Dry run over ') . $demos->count() . ' demo(s).');
        $this->newLine();

        $moved = 0;

        foreach ($demos as $demo) {
            $this->line(sprintf('  %d  %s  physics=%s', $demo->id, $demo->original_filename, $demo->physics));

            if ($demo->comps_withdrawn_at) {
                // With the timestamp, because "the player withdrew it" is a
                // claim about something that happened at a moment, and reading
                // it without the moment leaves you unable to tell a real
                // withdrawal from a column somebody's tooling filled in.
                $this->line('      <fg=yellow>the player took this run out of comps themselves on '
                    . $demo->comps_withdrawn_at . ', leaving it alone</>');

                continue;
            }

            $entries = CompSubmission::where('uploaded_demo_id', $demo->id)
                ->with('round')
                ->get();

            $frozen = $entries->filter(fn (CompSubmission $e) => $e->round?->status === 'finished');

            if ($frozen->isNotEmpty()) {
                $this->line('      <fg=red>in a finished round (' . $frozen->pluck('comp_round_id')->implode(', ')
                    . '), whose standings are frozen. Skipped.</>');

                continue;
            }

            $wanted = strtolower(explode('.', (string) $demo->physics)[0]);

            foreach ($entries as $entry) {
                $this->line(sprintf(
                    '      entry #%d round %d is %s, the demo is %s',
                    $entry->id,
                    $entry->comp_round_id,
                    $entry->physics,
                    $wanted
                ));
            }

            if (! $apply) {
                $this->line('      would drop ' . $entries->count() . ' entry(ies) and let the guard decide again');

                continue;
            }

            $entries->each->delete();

            $guard->apply($demo->fresh());

            $now = CompSubmission::where('uploaded_demo_id', $demo->id)->get();

            if ($now->isEmpty()) {
                $this->line('      <fg=yellow>the guard did not take it back. Nothing is entered now.</>');

                continue;
            }

            $moved++;

            foreach ($now as $entry) {
                $this->line(sprintf(
                    '      <fg=green>entry #%d round %d physics=%s status=%s</>',
                    $entry->id,
                    $entry->comp_round_id,
                    $entry->physics,
                    $entry->status
                ));
            }
        }

        $this->newLine();
        $this->info($apply
            ? "Re-entered {$moved} demo(s)."
            : 'Nothing was written. Re-run with --apply.');

        return self::SUCCESS;
    }
}
