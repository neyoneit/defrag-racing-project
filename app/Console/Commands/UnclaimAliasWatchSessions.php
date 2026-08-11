<?php

namespace App\Console\Commands;

use App\Models\DefragliveWatchSession;
use App\Models\MddProfile;
use App\Models\User;
use App\Models\UserAlias;
use App\Services\DefragliveWatchService;
use Illuminate\Console\Command;

/**
 * Hands back the watch sessions an alias claimed for someone else.
 *
 * A watched player used to be resolved to an account three ways: the mdd_id the
 * game reports for that client, the same by nick against the live player list,
 * and - wrongly - the alias table. The first two are a login. The third is only
 * a nick MDD once saw that account use, and nothing stops a different player
 * from using it too: frog has "suburb" among his aliases, so an evening of
 * watching by a suburb who was not logged in as anyone was credited to frog.
 *
 * The alias step is gone from DefragliveWatchService::resolve(). This is for the
 * identity it already stamped on stored sessions.
 *
 * **It cannot be run blind, which is why --nick is required.** The alias step
 * was a fallback: it only ran when the game reported no login for that client.
 * A stored session carries no trace of which step answered, and most of these
 * players were logged in while being spectated - clearing every nick an alias
 * happens to cover would split real players away from their own watch time. The
 * listing is a shortlist for a human, not a verdict.
 *
 * Handing a nick back is not permanent. Identity is also read back off the
 * sessions themselves - a nick the game did report a login for is applied to
 * that nick's other sessions - so if such a player is spectated while logged in,
 * their sessions group under the account again on their own.
 */
class UnclaimAliasWatchSessions extends Command
{
    protected $signature = 'defraglive:unclaim-alias-sessions
        {--nick=* : Clean nick to hand back, repeatable. Without any, this only lists}
        {--mdd= : Only the given account, for a nick two accounts both claim}
        {--apply : Actually clear the identity. Without this nothing is written}';

    protected $description = 'Drop the account an alias (not a login) put on watch sessions';

    public function handle(DefragliveWatchService $watch): int
    {
        $apply = (bool) $this->option('apply');
        $wanted = array_map(fn ($n) => $watch->cleanName((string) $n), (array) $this->option('nick'));
        $onlyMdd = $this->option('mdd') ? (int) $this->option('mdd') : null;

        $groups = $this->candidates($watch);

        if (! $groups) {
            $this->info('No session is held by an alias.');

            return self::SUCCESS;
        }

        if (! $wanted) {
            $this->listCandidates($watch, $groups);

            return self::SUCCESS;
        }

        $picked = array_filter($groups, fn ($g) => in_array($g['nick'], $wanted, true)
            && ($onlyMdd === null || $g['mdd_id'] === $onlyMdd));

        if (! $picked) {
            $this->error('None of those nicks is on an account through an alias.');

            return self::FAILURE;
        }

        $ids = array_merge(...array_column($picked, 'ids'));

        foreach ($picked as $g) {
            $this->line('  '.$g['nick'].' - '.count($g['ids']).' session(s), '
                .round($g['seconds'] / 60).' min, off account '.$g['mdd_id']);
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Nothing was written. Add --apply to hand these back.');

            return self::SUCCESS;
        }

        $journal = storage_path('logs/unclaim-alias-sessions-'.now()->format('Ymd-His').'.json');
        file_put_contents($journal, json_encode(
            DefragliveWatchSession::whereIn('id', $ids)
                ->get(['id', 'mdd_id', 'user_id', 'player_name_clean'])
                ->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        $this->line('Old identities written to '.$journal);

        DefragliveWatchSession::whereIn('id', $ids)->update(['mdd_id' => null, 'user_id' => null]);

        $this->info('Cleared '.count($ids).' session(s).');

        return self::SUCCESS;
    }

    /**
     * Sessions whose account an alias could have supplied, grouped per nick and
     * account. A nick that IS the account's own name is left out: the live
     * player list would have matched it by itself, so the alias explains nothing.
     */
    private function candidates(DefragliveWatchService $watch): array
    {
        // No approval filter - resolve() had none either, so an unapproved alias
        // claimed sessions just the same.
        $claims = [];

        foreach (UserAlias::whereNotNull('mdd_id')->get(['mdd_id', 'alias']) as $alias) {
            $claims[$watch->cleanName((string) $alias->alias)][] = (int) $alias->mdd_id;
        }

        $groups = [];

        $sessions = DefragliveWatchSession::whereNotNull('mdd_id')
            ->get(['id', 'mdd_id', 'player_name_clean', 'seconds']);

        foreach ($sessions as $s) {
            $nick = (string) $s->player_name_clean;
            $mddId = (int) $s->mdd_id;

            if (! in_array($mddId, $claims[$nick] ?? [], true)) {
                continue;
            }

            if (in_array($nick, $this->accountNames($watch, $mddId), true)) {
                continue;
            }

            $key = $nick.'|'.$mddId;
            $groups[$key] = $groups[$key] ?? ['nick' => $nick, 'mdd_id' => $mddId, 'ids' => [], 'seconds' => 0];
            $groups[$key]['ids'][] = (int) $s->id;
            $groups[$key]['seconds'] += (int) $s->seconds;
        }

        usort($groups, fn ($a, $b) => $b['seconds'] <=> $a['seconds']);

        return $groups;
    }

    private function listCandidates(DefragliveWatchService $watch, array $groups): void
    {
        $sessions = array_sum(array_map(fn ($g) => count($g['ids']), $groups));

        $this->info($sessions.' session(s) across '.count($groups)
            .' nick(s) sit on an account an alias also covers.');
        $this->comment('Most of them are that player, logged in. Pass --nick for the ones that are not.');
        $this->newLine();

        foreach ($groups as $g) {
            $profile = $watch->cleanName((string) MddProfile::where('id', $g['mdd_id'])->value('name'));
            $this->line('  '.str_pad(mb_substr($g['nick'], 0, 24), 26)
                .' credited to '.str_pad((string) $g['mdd_id'], 7).str_pad($profile, 20)
                .count($g['ids']).' sessions, '.round($g['seconds'] / 60).' min');
        }
    }

    /** The names an account itself goes by, cleaned. */
    private function accountNames(DefragliveWatchService $watch, int $mddId): array
    {
        $names = [MddProfile::where('id', $mddId)->value('name')];

        foreach (User::where('mdd_id', $mddId)->pluck('name') as $name) {
            $names[] = $name;
        }

        return array_values(array_filter(array_map(
            fn ($n) => $watch->cleanName((string) $n),
            $names
        )));
    }
}
