<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Accounts whose own name is somebody else's approved alias, and what that
 * has already cost.
 *
 * Matching resolves a nick through the alias table and takes the answer when
 * exactly one user owns it. An account is not consulted about its own name.
 * So a person who once played two rounds under your nick, and had that nick
 * recorded as an alias, quietly outranks you - and your demos land on their
 * profile.
 *
 * Two runs of one player reached the wrong account that way in August 2026.
 * This exists to say how many others did before anything is changed: moving
 * demos between real people on a rule nobody has measured is how a fix
 * becomes the next incident.
 *
 * Reads. Changes nothing, ever.
 */
class CheckNameCollisions extends Command
{
    protected $signature = 'demos:check-name-collisions
                            {--show=40 : how many accounts to print}';

    protected $description = 'Accounts whose name is another user\'s alias, and the demos that went the wrong way';

    public function handle(): int
    {
        $collisions = DB::table('users as u')
            ->join('user_aliases as a', function ($join) {
                $join->on(DB::raw('LOWER(a.alias)'), '=', DB::raw('LOWER(u.name)'));
            })
            ->where('a.is_approved', true)
            ->whereNotNull('a.user_id')
            ->whereColumn('a.user_id', '!=', 'u.id')
            ->select('u.id as account_id', 'u.name as account_name', 'a.user_id as alias_owner', 'a.usage_count')
            ->get()
            ->groupBy('account_id');

        $this->line('accounts whose name is somebody else\'s alias: ' . $collisions->count());
        $this->newLine();

        $rows = [];
        $totalDemos = 0;
        $totalRecords = 0;

        foreach ($collisions as $accountId => $group) {
            $first = $group->first();
            $name = (string) $first->account_name;
            $owners = $group->pluck('alias_owner')->unique()->values();

            // Demos that carry this account's nick but sit on one of the
            // accounts that merely hold it as an alias. Matched on the q3df
            // login as well as the parsed name, because the login is what
            // tier 2 of the matcher actually reads.
            $demos = UploadedDemo::withUnreleasedComps()
                ->whereIn('user_id', $owners)
                ->where(function ($q) use ($name) {
                    $q->whereRaw('LOWER(player_name) = ?', [mb_strtolower($name)])
                        ->orWhereRaw('LOWER(q3df_login_name) = ?', [mb_strtolower($name)]);
                })
                ->get(['id', 'record_id', 'manually_assigned']);

            if ($demos->isEmpty()) {
                continue;
            }

            // A demo sitting on a record is the expensive kind: the record is
            // somebody's ranked time, and moving the demo puts that pairing in
            // question rather than settling it.
            $onRecords = $demos->whereNotNull('record_id')->count();
            $byHand = $demos->where('manually_assigned', true)->count();

            $totalDemos += $demos->count();
            $totalRecords += $onRecords;

            $rows[] = [
                $accountId,
                $this->plain($name),
                $owners->implode(', '),
                $demos->count(),
                $onRecords,
                $byHand,
            ];
        }

        usort($rows, fn ($a, $b) => $b[3] <=> $a[3]);

        if ($rows) {
            $this->table(
                ['account', 'name', 'held by', 'demos', 'on a record', 'set by hand'],
                array_slice($rows, 0, (int) $this->option('show'))
            );
        }

        $this->newLine();
        $this->line('accounts actually affected: ' . count($rows));
        $this->line('demos on the wrong account: ' . $totalDemos);
        $this->line('of those, paired to a record: ' . $totalRecords);
        $this->newLine();
        $this->comment('Nothing was changed. A demo set by hand was somebody\'s decision and must stay put.');

        return self::SUCCESS;
    }

    private function plain(string $name): string
    {
        return trim((string) preg_replace('/\^[0-9A-Za-z]/', '', $name));
    }
}
