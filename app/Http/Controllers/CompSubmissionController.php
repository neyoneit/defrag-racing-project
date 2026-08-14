<?php

namespace App\Http\Controllers;

use App\Models\CompDemoReport;
use App\Models\CompMapReport;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\UploadedDemo;
use App\Services\Comps\SubmissionIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
    public function store(Request $request, CompRound $round, SubmissionIntake $intake)
    {
        // No physics field. The demo says which it is and the parser reads it
        // out; asking would only give somebody a way to get their own file
        // wrong. See SubmissionValidator.
        $data = $request->validate([
            'demo' => ['required', 'file', 'max:' . SubmissionIntake::MAX_KB],
            'is_highlight' => ['sometimes', 'boolean'],
            // Unix seconds from the browser's File object. Not proof of
            // anything - the client sends it - but a file dated before this
            // round's ballot opened is an old run, and that is the direction
            // the check is used in.
            'file_mtime' => ['nullable', 'integer', 'min:0'],
        ]);

        abort_unless($request->user(), 403);

        // Who may enter at all - a linked Q3DF.org profile, same as voting.
        // The page hides the form when this would fail; the check is here
        // because a hidden form is not a rule.
        if ($reason = $intake->userRejectionReason($request->user())) {
            return back()->withErrors(['demo' => $reason]);
        }

        $file = $request->file('demo');
        $hash = $intake->hash($file);

        // Every rule about what may be entered lives in SubmissionIntake, so
        // this page and the launcher's endpoint cannot drift apart. A rule that
        // only one of them enforces does not surface as an error - it surfaces
        // as a run that counted on one route and not the other, weeks later, in
        // the standings.
        if ($reason = $intake->rejectionReason($round, $file, $hash)) {
            return back()->withErrors(['demo' => $reason]);
        }

        $mtime = ! empty($data['file_mtime'])
            ? Carbon::createFromTimestamp((int) $data['file_mtime'])
            : null;

        $intake->accept(
            $round,
            $request->user(),
            $file,
            $hash,
            (bool) ($data['is_highlight'] ?? false),
            // A clock running ahead would write a future date, and a future
            // date passes every "made after the ballot opened" check there is.
            clientMtime: $mtime?->isFuture() ? now() : $mtime,
        );

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
                'kind' => CompDemoReport::ENTRY,
                'uploaded_demo_id' => $submission->uploaded_demo_id,
                'reason' => $data['reason'],
                'status' => 'open',
            ]
        );

        return back();
    }

    /**
     * "My own demo went in and nothing happened to it."
     *
     * The other half of reporting, and the one that comes up far more often.
     * A demo the parser could not read has no entry to hang a report on, and
     * neither does a run being held for a map that is still being voted on -
     * which is exactly when somebody needs to ask. So this points at the demo.
     *
     * Only your own: this is asking for help with a file of yours, not an
     * accusation about somebody else's, and the demos it is raised from are
     * ones nobody else can even see.
     */
    public function reportOwnDemo(Request $request, UploadedDemo $demo)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        abort_unless($request->user(), 403);
        abort_unless($request->user()->id === $demo->user_id, 403);

        CompDemoReport::updateOrCreate(
            [
                'uploaded_demo_id' => $demo->id,
                'reported_by' => $request->user()->id,
                'kind' => CompDemoReport::HELP,
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
