<?php

namespace App\Http\Controllers;

use App\Models\PlayerSelfReport;
use App\Models\RecordFlag;
use App\Models\ServerdemoValidationCase;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The public record of every report and what came of it.
 *
 * This page is the condition the whole enforcement rests on: nothing gets
 * acted on before people can see what is being acted on. So it is written to
 * be readable by somebody who does not trust us, which decides three things.
 *
 * A case is named once it is CLOSED, whichever way it went. Publishing only
 * the guilty ones would turn the log into a list of convictions and hide
 * every time the process cleared somebody - which is the half that shows the
 * process works at all.
 *
 * A case in progress is NOT named. It is an accusation nobody has checked
 * yet, and a name against an open accusation is a punishment handed out
 * before the verdict. The count, the stage and the age are public, so nobody
 * can quietly sit on a case either.
 *
 * Who reported what is never public, at any stage. The reporters are the one
 * group with something to lose from transparency here, and a report is not
 * evidence - it is a request to go look.
 */
class ValidationLogController extends Controller
{
    /** How the ladder reads to somebody who has not seen the admin panel. */
    private const STAGE_LABELS = [
        'assigned' => 'With a validator',
        'second_opinion' => 'Second opinion',
        'all_validators' => 'Open to all validators',
        'admin' => 'With the admin',
    ];

    private const OUTCOME_LABELS = [
        'upheld' => 'Upheld',
        'dismissed' => 'Cleared',
        'inconclusive' => 'Inconclusive',
    ];

    public function index(Request $request)
    {
        return Inertia::render('ValidationLog', [
            'stats' => $this->stats(),
            'closed' => $this->closed(),
            'open' => $this->open(),
            'selfReports' => $this->selfReports(),
            'minReports' => (int) config('serverdemos.validation.min_reports', 2),
        ]);
    }

    /**
     * The numbers that say how much of this is happening at all. Reports are
     * counted as reports, not as cases - several reports about one player are
     * one case, and both figures are worth seeing.
     */
    private function stats(): array
    {
        return [
            'reports' => RecordFlag::count(),
            'awaiting_admin' => RecordFlag::awaitingClearance()->count(),
            'in_validation' => ServerdemoValidationCase::whereNull('validation_closed_at')->count(),
            'closed' => ServerdemoValidationCase::whereNotNull('validation_closed_at')->count(),
            'upheld' => ServerdemoValidationCase::where('validation_outcome', 'upheld')->count(),
            'dismissed' => ServerdemoValidationCase::where('validation_outcome', 'dismissed')->count(),
            'inconclusive' => ServerdemoValidationCase::where('validation_outcome', 'inconclusive')->count(),
            'self_reports' => PlayerSelfReport::count(),
        ];
    }

    /** Decided cases, named, newest first. */
    private function closed()
    {
        return ServerdemoValidationCase::query()
            ->withCount('flags')
            // Eager loaded because every row needs its report types below,
            // and asking per row is 200 queries on a page anyone can open.
            ->with('flags:id,validation_case_id,flag_type')
            ->whereNotNull('validation_closed_at')
            ->orderByDesc('validation_closed_at')
            ->limit(200)
            ->get()
            ->map(fn (ServerdemoValidationCase $case) => [
                'id' => $case->id,
                'player' => $case->subjectLabel(),
                'user_id' => $case->subject_user_id,
                'kind' => $case->kind,
                'runs' => $case->flags_count,
                'outcome' => self::OUTCOME_LABELS[$case->validation_outcome] ?? 'Closed',
                'outcome_key' => $case->validation_outcome,
                'opened_at' => $case->created_at?->toIso8601String(),
                'closed_at' => $case->validation_closed_at?->toIso8601String(),
                'reasons' => $this->reasonsFor($case),
            ]);
    }

    /**
     * Cases still being looked at. Everything here is deliberately
     * unidentifiable: no name, no map, no time - a run on a map IS a name to
     * anyone who reads the leaderboard.
     */
    private function open()
    {
        return ServerdemoValidationCase::query()
            ->withCount('flags')
            ->whereNull('validation_closed_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ServerdemoValidationCase $case) => [
                'id' => $case->id,
                'kind' => $case->kind,
                'runs' => $case->flags_count,
                'stage' => self::STAGE_LABELS[$case->validation_stage] ?? $case->validation_stage,
                'opened_at' => $case->created_at?->toIso8601String(),
            ]);
    }

    /**
     * Times their own owners took down. Named, because the time itself was
     * public and its disappearance from the map is public too - explaining
     * where it went is the only version of this that is not a quiet edit.
     */
    private function selfReports()
    {
        return PlayerSelfReport::query()
            ->with('user:id,name,plain_name,profile_photo_path,country')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (PlayerSelfReport $report) => [
                'id' => $report->id,
                'player' => $report->player_name,
                'user_id' => $report->user_id,
                'mapname' => $report->mapname,
                'physics' => trim(($report->physics ?? '') . ' ' . ($report->mode ?? '')),
                'time' => $report->time,
                'reason' => RecordFlagController::FLAG_TYPES[$report->reason] ?? $report->reason,
                'note' => $report->note,
                'created_at' => $report->created_at?->toIso8601String(),
            ]);
    }

    /**
     * What the case is about, as report types. The individual runs stay out
     * of it: "sv_cheats, twice" is what a reader needs, and naming the maps
     * would republish the accusation run by run even where it was dismissed.
     */
    private function reasonsFor(ServerdemoValidationCase $case): array
    {
        return $case->flags
            ->groupBy('flag_type')
            ->map(fn ($group, $type) => [
                'label' => RecordFlagController::FLAG_TYPES[$type] ?? $type,
                'count' => $group->count(),
            ])
            ->values()
            ->all();
    }
}
