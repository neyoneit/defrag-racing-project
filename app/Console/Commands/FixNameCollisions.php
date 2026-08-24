<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Give an account back the demos an alias took from it.
 *
 * The matcher used to resolve a nick through the alias table without ever
 * consulting an account about its own name, so a nick somebody else once
 * wore outranked the person registered under it. NameMatcher decides the
 * other way round now; this moves what the old rule already misfiled.
 *
 * Two rules it will not break:
 *
 *   A demo somebody assigned by hand stays where it is. That was a person's
 *   decision about a particular file and it outranks any rule.
 *
 *   A demo paired to an online record keeps that pairing. The record is a
 *   ranked time belonging to an account, and moving the demo without it
 *   would leave a run pointing at somebody else's result. Those are listed
 *   and left for a person.
 *
 * The offline times ride along on their own: an offline record has no owner
 * of its own, it hangs off the demo, so a demo that moves takes its times
 * with it and nothing is duplicated.
 *
 * Dry run unless --apply, and every write is journalled for --revert.
 */
class FixNameCollisions extends Command
{
    protected $signature = 'demos:fix-name-collisions
        {--apply : Write the moves. Without this nothing is saved}
        {--revert= : Put back everything a journal file recorded}';

    protected $description = 'Move demos to the account whose own name they carry';

    public function handle(): int
    {
        if ($path = $this->option('revert')) {
            return $this->revert($path);
        }

        $apply = (bool) $this->option('apply');

        $collisions = DB::table('users as u')
            ->join('user_aliases as a', function ($join) {
                $join->on(DB::raw('LOWER(a.alias)'), '=', DB::raw('LOWER(u.name)'));
            })
            ->where('a.is_approved', true)
            ->whereNotNull('a.user_id')
            ->whereColumn('a.user_id', '!=', 'u.id')
            ->select('u.id as account_id', 'u.name as account_name', 'a.user_id as alias_owner')
            ->get()
            ->groupBy('account_id');

        $moved = 0;
        $kept = 0;
        $onRecords = [];
        $journal = [];

        foreach ($collisions as $accountId => $group) {
            $name = (string) $group->first()->account_name;
            $owners = $group->pluck('alias_owner')->unique()->values();

            $demos = UploadedDemo::withUnreleasedComps()
                ->whereIn('user_id', $owners)
                ->where(function ($q) use ($name) {
                    $q->whereRaw('LOWER(player_name) = ?', [mb_strtolower($name)])
                        ->orWhereRaw('LOWER(q3df_login_name) = ?', [mb_strtolower($name)]);
                })
                ->get(['id', 'user_id', 'record_id', 'manually_assigned', 'original_filename']);

            if ($demos->isEmpty()) {
                continue;
            }

            $this->line(sprintf('  #%s %s  <- %d demo(s)', $accountId, $this->plain($name), $demos->count()));

            foreach ($demos as $demo) {
                if ($demo->manually_assigned) {
                    $kept++;
                    $this->line('      <fg=yellow>demo ' . $demo->id . ' was assigned by hand, left alone</>');

                    continue;
                }

                if ($demo->record_id) {
                    $onRecords[] = $demo->id;
                    $kept++;
                    $this->line('      <fg=yellow>demo ' . $demo->id . ' is paired to record ' . $demo->record_id
                        . ', left alone - worth a look</>');

                    continue;
                }

                $moved++;

                if (! $apply) {
                    continue;
                }

                $journal[] = ['demo' => $demo->id, 'from' => $demo->user_id, 'to' => (int) $accountId];

                DB::table('uploaded_demos')->where('id', $demo->id)->update([
                    'user_id' => (int) $accountId,
                    'suggested_user_id' => (int) $accountId,
                    'match_method' => 'q3df_account',
                    'updated_at' => now(),
                ]);
            }
        }

        $this->newLine();
        $this->line(($apply ? 'moved:      ' : 'would move: ') . $moved);
        $this->line('left alone: ' . $kept);

        if ($onRecords) {
            $this->newLine();
            $this->comment('Paired to a record, decide by hand: ' . implode(', ', $onRecords));
        }

        if ($apply && $journal) {
            $file = storage_path('app/name-collisions/' . now()->format('Y-m-d_His') . '.json');
            @mkdir(dirname($file), 0755, true);
            file_put_contents($file, json_encode($journal, JSON_PRETTY_PRINT));

            $this->newLine();
            $this->info('Written down in: ' . $file);
            $this->line('Undo this run with: php artisan demos:fix-name-collisions --revert=' . $file);
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Nothing was written. Re-run with --apply.');
        }

        return self::SUCCESS;
    }

    /** Put every demo in the journal back on the account it came from. */
    private function revert(string $path): int
    {
        if (! is_file($path)) {
            $this->error('No such journal: ' . $path);

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('That journal cannot be read.');

            return self::FAILURE;
        }

        $back = 0;

        foreach ($rows as $row) {
            DB::table('uploaded_demos')->where('id', $row['demo'])->update([
                'user_id' => $row['from'],
                'updated_at' => now(),
            ]);
            $back++;
        }

        $this->info("Put {$back} demo(s) back.");

        return self::SUCCESS;
    }

    private function plain(string $name): string
    {
        return trim((string) preg_replace('/\^[0-9A-Za-z]/', '', $name));
    }
}
