<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDemoJob;
use App\Models\CompDemoReport;
use App\Models\CompMapReport;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\UploadedDemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Entering a run, and reporting one.
 *
 * Nothing enters comps by itself. Serverdemos are collected off bundle servers
 * automatically and are not read here at all - if you want a time to count you
 * upload the demo, offline or online, through this page. When an online demo
 * happens to line up with a scraped record that is noted, and it changes
 * nothing about the entry.
 *
 * Upload as often as you like. People improve through a round and only their
 * best valid time is scored, so holding somebody to one attempt would just
 * mean their first mediocre run is the one that counts.
 */
class CompSubmissionController extends Controller
{
    /** Same ceiling the ordinary demo upload uses. */
    private const MAX_KB = 51200;

    public function store(Request $request, CompRound $round)
    {
        // No physics field. The demo says which it is and the parser reads it
        // out; asking would only give somebody a way to get their own file
        // wrong. See SubmissionValidator.
        $data = $request->validate([
            'demo' => ['required', 'file', 'max:' . self::MAX_KB],
            'is_highlight' => ['sometimes', 'boolean'],
        ]);

        abort_unless($request->user(), 403);

        abort_unless(
            $round->acceptsUploads() && $round->ends_at->isFuture(),
            403,
            __('This round is closed. Runs uploaded after the deadline do not count.')
        );

        $file = $request->file('demo');

        if (strtolower($file->getClientOriginalExtension()) !== 'dm_68') {
            return back()->withErrors(['demo' => __('That is not a Quake 3 demo file (.dm_68).')]);
        }

        // Same duplicate check the ordinary upload does. People improve through
        // a round and upload again, which is the point - but the same file
        // twice is a slip, not an improvement, and ten copies of one run in
        // somebody's entry list helps nobody.
        //
        // md5, because `uploaded_demos.file_hash` is what every other upload
        // path writes an md5 into and the column is varchar(32) with a unique
        // index on it. A sha256 here was silently truncated to its first 32
        // characters, which is the hash of nothing: no other path could ever
        // compute that value, so the same demo re-uploaded through /demos or
        // the launcher deduped against nothing and became a second, public row
        // in the middle of a round.
        $hash = md5_file($file->getRealPath());

        $duplicate = UploadedDemo::withUnreleasedComps()
            ->where('file_hash', $hash)
            ->whereNotIn('status', ['failed', 'failed-validity', 'unsupported-version'])
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['demo' => __('You have already uploaded this exact demo.')]);
        }

        $submission = DB::transaction(function () use ($request, $round, $file, $data, $hash) {
            $demo = UploadedDemo::create([
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => '',
                'file_size' => $file->getSize(),
                'file_hash' => $hash,
                'user_id' => $request->user()->id,
                'status' => 'uploaded',
                // Out of /demos, the map page, profiles and the launcher until
                // the round is over. The demo is the route.
                'comps_hidden_until' => $round->ends_at,
            ]);

            $directory = storage_path("app/demos/temp/{$demo->id}");

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $name = $file->getClientOriginalName();
            $file->move($directory, $name);
            $demo->update(['file_path' => "demos/temp/{$demo->id}/{$name}"]);

            // Pending: the entry exists straight away so the uploader can see
            // it, and settles once the queue has parsed the demo. See
            // SubmissionValidator.
            $submission = CompSubmission::create([
                'comp_round_id' => $round->id,
                'user_id' => $request->user()->id,
                'mdd_id' => $request->user()->mdd_id,
                'physics' => null,
                'time' => 0,
                'uploaded_demo_id' => $demo->id,
                'is_highlight' => (bool) ($data['is_highlight'] ?? false),
                'status' => 'pending',
            ]);

            ProcessDemoJob::dispatch($demo);

            return $submission;
        });

        Log::info("[comps] submission {$submission->id} queued for round {$round->id}");

        return back();
    }

    /**
     * Withdraw your own entry, while the round is still running. Afterwards it
     * is part of the standings and no longer yours alone to remove.
     */
    public function destroy(Request $request, CompSubmission $submission)
    {
        abort_unless($request->user()?->id === $submission->user_id, 403);
        abort_unless($submission->round?->acceptsUploads(), 403, __('This round is closed.'));

        $submission->delete();

        return back();
    }

    /**
     * Report an entry. An admin looks at the reason and, if it stands, the
     * entry drops out of comps - the demo itself stays in the demo database,
     * because being wrong for a competition is not a reason to erase a file.
     */
    public function reportDemo(Request $request, CompSubmission $submission)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        abort_unless($request->user(), 403);

        CompDemoReport::updateOrCreate(
            [
                'comp_submission_id' => $submission->id,
                'reported_by' => $request->user()->id,
            ],
            [
                'reason' => $data['reason'],
                'status' => 'open',
            ]
        );

        return back();
    }

    /**
     * Report that a candidate cannot be finished in one of the physics.
     *
     * Approved by an admin it becomes a cpmonly / vq3only tag: the map leaves
     * that ballot at once and never enters that physics' pool again. Which is
     * why it is a report and not a switch - one person struggling with a map
     * is not the same as a map being impossible.
     */
    public function reportMap(Request $request, CompRound $round)
    {
        $data = $request->validate([
            'map_id' => ['required', 'integer'],
            'physics' => ['required', 'in:cpm,vq3'],
        ]);

        abort_unless($request->user(), 403);
        abort_unless($round->isVoting(), 403, __('Voting for this round is closed.'));

        $onBallot = $round->candidates()->where('map_id', $data['map_id'])->exists();
        abort_unless($onBallot, 404);

        CompMapReport::updateOrCreate(
            [
                'comp_round_id' => $round->id,
                'map_id' => $data['map_id'],
                'physics' => $data['physics'],
                'reported_by' => $request->user()->id,
            ],
            ['status' => 'open']
        );

        return back();
    }
}
