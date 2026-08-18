<?php

namespace App\Services\Comps;

use App\Jobs\ProcessDemoJob;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\UploadedDemo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Taking a demo in as an entry.
 *
 * Two doors lead here - the upload form on the comps page and the launcher's
 * endpoint - and they must agree on every rule, because a difference between
 * them would not show up as an error. It would show up as somebody's run
 * counting on one route and not the other, weeks later, in the standings.
 *
 * So the rules live here once: what a demo file is, what counts as the same
 * file twice, when a round still takes entries, and what an entry looks like
 * before the parser has read anything.
 */
class SubmissionIntake
{
    /** Same ceiling the ordinary demo upload uses, in kilobytes. */
    public const MAX_KB = 51200;

    /** Statuses that mean an earlier upload of this file failed and does not count. */
    private const FAILED_STATUSES = ['failed', 'failed-validity', 'unsupported-version'];

    /**
     * Why this person cannot enter a run, or null when they can.
     *
     * Comps pays a prize and hands out wildcards, and both are settled against
     * a Q3DF.org profile. An account without one is a sign-up form and nothing
     * more, which would make voting and winning as cheap as making another
     * address - so entering asks for the same linked profile voting already
     * does. Here rather than on one route, because the page and the launcher
     * must not be able to disagree about who may enter.
     */
    public function userRejectionReason(?User $user): ?string
    {
        if (! $user) {
            return __('Sign in to enter a run.');
        }

        if (! $user->hasVerifiedEmail()) {
            return __('Confirm your email address before entering a run.');
        }

        if (! $user->mdd_id) {
            return __('Link your Q3DF.org account to enter a run.');
        }

        return null;
    }

    /**
     * Why this file cannot be entered, or null when it can.
     *
     * Returns a finished sentence rather than a code: both callers show it to a
     * person, and a code would mean writing the same four sentences twice.
     */
    public function rejectionReason(CompRound $round, UploadedFile $file, string $hash): ?string
    {
        if (! $round->acceptsUploads() || ! $round->ends_at->isFuture()) {
            return __('This round is closed. Runs uploaded after the deadline do not count.');
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'dm_68') {
            return __('That is not a Quake 3 demo file (.dm_68).');
        }

        // People improve through a round and upload again, which is the point -
        // but the same file twice is a slip, not an improvement, and ten copies
        // of one run in somebody's entry list helps nobody.
        //
        // withUnreleasedComps() because the entry we are looking for is hidden
        // by definition: it is a comps entry in a round still being played, and
        // the global scope hides exactly those.
        $duplicate = UploadedDemo::withUnreleasedComps()
            ->where('file_hash', $hash)
            ->whereNotIn('status', self::FAILED_STATUSES)
            ->exists();

        return $duplicate ? __('You have already uploaded this exact demo.') : null;
    }

    /**
     * md5, because `uploaded_demos.file_hash` is what every upload path on the
     * site writes an md5 into, and the column is varchar(32) with a unique index
     * on it. A sha256 here was silently truncated to its first 32 characters -
     * the hash of nothing - so the file deduped against nothing and could be
     * published again through another route mid-round.
     */
    public function hash(UploadedFile $file): string
    {
        return md5_file($file->getRealPath());
    }

    /**
     * Store the demo, create the entry, queue the parse.
     *
     * The entry exists from this moment even though nothing is known about the
     * run yet: the parse happens on a queue, and making somebody wait on a
     * spinner to find out whether their file was accepted would be worse than
     * showing them a row that settles a minute later.
     *
     * `$autoEntered` marks an entry the launcher decided on from the filename
     * rather than one a person chose - see the column's migration for why the
     * two cannot be treated the same when the guess turns out wrong.
     */
    public function accept(
        CompRound $round,
        User $user,
        UploadedFile $file,
        string $hash,
        bool $isHighlight = false,
        bool $autoEntered = false,
        ?Carbon $clientMtime = null,
    ): CompSubmission {
        $submission = DB::transaction(function () use ($round, $user, $file, $hash, $isHighlight, $autoEntered, $clientMtime) {
            $demo = UploadedDemo::create([
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => '',
                'file_size' => $file->getSize(),
                'file_hash' => $hash,
                'user_id' => $user->id,
                'status' => 'uploaded',
                'source' => UploadedDemo::SOURCE_COMPS,
                // Out of /demos, the map page, profiles and the launcher until
                // the round is over. The demo is the route.
                'comps_hidden_until' => $round->ends_at,
                // When the file was written on the uploader's disk, if their
                // client told us. A run has to have been made after the ballot
                // opened, and this is the only date most online demos have.
                'client_file_mtime' => $clientMtime,
            ]);

            $directory = storage_path("app/demos/temp/{$demo->id}");

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $name = $file->getClientOriginalName();
            $file->move($directory, $name);
            $demo->update(['file_path' => "demos/temp/{$demo->id}/{$name}"]);

            $submission = CompSubmission::create([
                'comp_round_id' => $round->id,
                'user_id' => $user->id,
                'mdd_id' => $user->mdd_id,
                'physics' => null,
                'time' => 0,
                'uploaded_demo_id' => $demo->id,
                'is_highlight' => $isHighlight,
                'status' => 'pending',
                'auto_entered' => $autoEntered,
            ]);

            ProcessDemoJob::dispatch($demo);

            return $submission;
        });

        Log::info(sprintf(
            '[comps] submission %d queued for round %d%s',
            $submission->id,
            $round->id,
            $autoEntered ? ' (auto, from launcher)' : ''
        ));

        return $submission;
    }
}
