<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Models\Record;
use App\Services\NameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill for the match_method column on demos that were
 * already assigned before match_method was introduced. Pure inference
 * from existing fields — no auto-assign pipeline rerun, so record_id,
 * user_id, name_confidence, and matched_alias are not touched.
 *
 * Scope: status='assigned' AND record_id IS NOT NULL AND match_method
 * IS NULL. Offline-style assigned demos (record_id IS NULL) are skipped
 * because match_method is never populated for them by design.
 *
 * Inference order mirrors DemoAutoAssigner's PASS 0/1/2 priority so the
 * backfilled value is what the current pipeline would have written for
 * the same demo.
 */
class BackfillMatchMethod extends Command
{
    protected $signature = 'demos:backfill-match-method
        {--chunk=1000 : Batch size for chunkById streaming}
        {--dry-run : Count what would change without writing}';

    protected $description = 'Backfill match_method on demos assigned before the column existed';

    public function handle(NameMatcher $nameMatcher)
    {
        $chunkSize = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $query = UploadedDemo::where('status', 'assigned')
            ->whereNotNull('record_id')
            ->whereNull('match_method')
            ->whereNotNull('player_name');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to backfill.');
            return 0;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Backfilling match_method on {$total} demos...");

        // Preload record_id -> user_id map so we don't hit records table
        // once per demo. ~650k rows, ~10 MB, keyed by int — cheap.
        $this->info('Preloading record owner map...');
        $t0 = microtime(true);
        $recordUser = [];
        DB::table('records')
            ->select(['id', 'user_id'])
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunk(20000, function ($chunk) use (&$recordUser) {
                foreach ($chunk as $r) {
                    $recordUser[(int) $r->id] = (int) $r->user_id;
                }
            });
        $this->info('  -> ' . number_format(count($recordUser)) . ' records mapped in ' . round(microtime(true) - $t0, 1) . 's');

        $counts = [
            'q3df_colored_record' => 0,
            'q3df_plain_record'   => 0,
            'uploader_record'     => 0,
            'fuzzy_nick'          => 0,
            'skipped_no_owner'    => 0,
        ];

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $query->orderBy('id')->chunkById($chunkSize, function ($demos) use ($nameMatcher, &$recordUser, &$counts, $progressBar, $dryRun) {
            $updates = [];
            foreach ($demos as $demo) {
                $matchedUserId = $recordUser[(int) $demo->record_id] ?? null;
                if ($matchedUserId === null) {
                    $counts['skipped_no_owner']++;
                    $progressBar->advance();
                    continue;
                }

                $method = $this->inferMatchMethod($demo, $matchedUserId, $nameMatcher);
                $counts[$method]++;
                $updates[$demo->id] = $method;
                $progressBar->advance();
            }

            if (!$dryRun && !empty($updates)) {
                // CASE WHEN ... per chunk keeps it to one UPDATE statement.
                DB::transaction(function () use ($updates) {
                    foreach ($updates as $id => $method) {
                        UploadedDemo::where('id', $id)->update(['match_method' => $method]);
                    }
                });
            }
        });

        $progressBar->finish();
        $this->newLine();

        $this->info($dryRun ? 'Dry-run counts:' : 'Backfilled:');
        foreach ($counts as $method => $count) {
            if ($count > 0) {
                $this->info("  {$method}: " . number_format($count));
            }
        }
        return 0;
    }

    /**
     * Infer which match_method best describes how this demo was matched
     * to its Record, given the matched user_id (the record owner).
     *
     * Priority follows DemoAutoAssigner: q3df login > uploader > fuzzy.
     */
    protected function inferMatchMethod(UploadedDemo $demo, int $matchedUserId, NameMatcher $nameMatcher): string
    {
        // PASS 0: q3df login. Re-run matchByQ3dfLogin against current
        // alias data — if it still resolves to the record owner, the tier
        // (colored vs plain) tells us which variant to record.
        if ($demo->q3df_login_name || $demo->q3df_login_name_colored) {
            $loginMatch = $nameMatcher->matchByQ3dfLogin(
                $demo->q3df_login_name,
                $demo->q3df_login_name_colored
            );
            if ($loginMatch['user_id'] === $matchedUserId && $loginMatch['tier']) {
                return $loginMatch['tier'] . '_record';
            }
        }

        // PASS 1: uploader identity match. If the demo's uploader owns the
        // record, that's how live processor matched it.
        if ($demo->user_id !== null && (int) $demo->user_id === $matchedUserId) {
            return 'uploader_record';
        }

        // PASS 2: by elimination — name-based match (we don't reverify
        // that the name still resolves to $matchedUserId because it may
        // have changed since the original match, but the Record is the
        // authoritative link and its owner is what the match produced).
        return 'fuzzy_nick';
    }
}
