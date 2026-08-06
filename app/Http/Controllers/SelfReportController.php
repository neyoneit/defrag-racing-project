<?php

namespace App\Http\Controllers;

use App\Models\PlayerSelfReport;
use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Taking your own invalid time down, with no consequence attached.
 *
 * The amnesty is the point. Somebody who set a time years ago with the wrong
 * cvar has, right now, two options: say nothing and hope, or be reported and
 * argued over. Neither ends with the leaderboard being correct. This is a
 * third one, and it only works while it stays free AND private: no validator,
 * no verdict, no mark on the account, and nobody told. A withdrawal that shows
 * up anywhere others can read it is a confession, and people do not volunteer
 * confessions - so this is visible to the admin alone, not even to the
 * validators, and never on the public log.
 *
 * It takes the record off the leaderboard on the spot for the same reason. A
 * queue would mean the honest answer still leaves the wrong time standing for
 * a week, and the player has already told us it does not belong there - there
 * is nothing left to establish.
 *
 * The delete is a SOFT delete, so a misclick IS recoverable from the
 * withdrawn-times list in the admin panel. The page deliberately does not
 * mention that: told there is a way back, people stop reading what they ticked
 * and start asking for reversals, and the safety net turns into a queue. What
 * it says instead is true from where the player stands - they cannot take it
 * back themselves.
 */
class SelfReportController extends Controller
{
    /** How the list can be ordered, and what that means in SQL. */
    private const SORTS = [
        'date' => ['records.date_set', 'desc'],
        'rank' => ['records.rank', 'asc'],
        'name' => ['records.mapname', 'asc'],
        'map_added' => ['maps.date_added', 'desc'],
    ];

    /** Rows per page. Thumbnails make a longer page heavy for no gain. */
    private const PAGE_SIZE = 60;

    public function index(Request $request)
    {
        $user = $request->user();

        // Blocked players still get the page, with the reason on it. Silently
        // hiding the feature would leave somebody clicking a dead link and
        // guessing, and the point of the block is that it was earned.
        if ($user->amnesty_blocked_at) {
            return Inertia::render('SelfReport', [
                'hasMddAccount' => (bool) $user->mdd_id,
                'blocked' => [
                    'since' => $user->amnesty_blocked_at?->toIso8601String(),
                    'reason' => $user->amnesty_blocked_reason,
                ],
                'records' => ['data' => []],
                'reasons' => RecordFlagController::FLAG_TYPES,
                'mine' => [],
            ]);
        }

        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort');
        $sort = isset(self::SORTS[$sort]) ? $sort : 'date';

        $records = ['data' => []];
        $total = 0;

        if ($user->mdd_id) {
            [$column, $direction] = self::SORTS[$sort];

            $total = Record::where('mdd_id', $user->mdd_id)->count();

            // Runs already waiting on an admin drop out of the pickable list -
            // they are in "runs you have sent" below, and offering them again
            // only invites a duplicate request.
            $pending = PlayerSelfReport::pending()
                ->where('user_id', $user->id)
                ->pluck('record_id')
                ->filter()
                ->all();

            $records = Record::query()
                // Left join, not a relation: the list sorts by the map's own
                // date, and a record whose map is missing from the maps table
                // still has to appear rather than being silently dropped.
                ->leftJoin('maps', 'maps.name', '=', 'records.mapname')
                ->where('records.mdd_id', $user->mdd_id)
                ->when($pending, fn ($query) => $query->whereNotIn('records.id', $pending))
                ->when($search !== '', fn ($query) => $query->where('records.mapname', 'like', '%' . $search . '%'))
                ->orderBy($column, $direction)
                // Paginated rather than capped: with a thousand runs behind it,
                // "search for the rest" is not a way to reach the rest.
                ->paginate(self::PAGE_SIZE, [
                    'records.id',
                    'records.mapname',
                    'records.physics',
                    'records.mode',
                    'records.gametype',
                    'records.time',
                    'records.date_set',
                    'records.rank',
                    'maps.thumbnail as map_thumbnail',
                    'maps.date_added as map_added',
                ])
                ->withQueryString()
                ->through(fn ($record) => [
                    'id' => $record->id,
                    'mapname' => $record->mapname,
                    'physics' => $record->physics,
                    'mode' => $record->mode,
                    'gametype' => $record->gametype,
                    'time' => $record->time,
                    'date_set' => $record->date_set,
                    'rank' => $record->rank,
                    'map_thumbnail' => $record->map_thumbnail,
                    'map_added' => $record->map_added,
                ]);
        }

        return Inertia::render('SelfReport', [
            'hasMddAccount' => (bool) $user->mdd_id,
            'records' => $records,
            'search' => $search,
            'sort' => $sort,
            'totalRecords' => $total,
            'reasons' => RecordFlagController::FLAG_TYPES,
            // Your own withdrawals, with the reason you gave. Private like the
            // rest of it, but you are allowed to see what you did and why -
            // months later "which runs did I take down" is a fair question and
            // the answer should not only exist in the admin panel.
            'handlingOptions' => PlayerSelfReport::HANDLING,
            'mine' => PlayerSelfReport::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (PlayerSelfReport $report) => [
                    'id' => $report->id,
                    'mapname' => $report->mapname,
                    'physics' => $report->physics,
                    'mode' => $report->mode,
                    'time' => $report->time,
                    'reason' => RecordFlagController::FLAG_TYPES[$report->reason] ?? $report->reason,
                    'handling' => $report->handling,
                    'note' => $report->note,
                    'processed' => $report->isProcessed(),
                    'created_at' => $report->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Withdraw one or more runs in a single go. Somebody cleaning up after an
     * old habit is rarely cleaning up exactly one map, and making them repeat
     * a confirmation forty times is how a good intention turns into "later".
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->amnesty_blocked_at) {
            return back()->withErrors([
                'record_ids' => 'You no longer have access to the amnesty.',
            ]);
        }

        $data = $request->validate([
            'record_ids' => ['required', 'array', 'min:1', 'max:100'],
            'record_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'in:' . implode(',', array_keys(RecordFlagController::FLAG_TYPES))],
            'handling' => ['required', 'string', 'in:' . implode(',', array_keys(PlayerSelfReport::HANDLING))],
            'note' => ['nullable', 'string', 'max:500'],
            'confirm' => ['accepted'],
        ], [
            'record_ids.required' => 'Pick at least one run.',
            'confirm.accepted' => 'Tick the box to send it.',
        ]);

        // Yours means yours. The MDD id is what ties a record to a person, so
        // an account without one cannot withdraw anything, and the ownership
        // check is part of the QUERY rather than a loop over what was posted -
        // anything not covered by it simply never comes back.
        $records = $user->mdd_id
            ? Record::whereIn('id', $data['record_ids'])->where('mdd_id', $user->mdd_id)->get()
            : collect();

        if ($records->isEmpty()) {
            return back()->withErrors([
                'record_ids' => 'Those runs are not yours.',
            ]);
        }

        // Already asked about, so asking again would only duplicate the queue.
        $alreadyAsked = PlayerSelfReport::pending()
            ->whereIn('record_id', $records->pluck('id'))
            ->pluck('record_id')
            ->all();

        $records = $records->reject(fn (Record $record) => in_array($record->id, $alreadyAsked, true));

        if ($records->isEmpty()) {
            return back()->withErrors([
                'record_ids' => 'Those runs are already waiting to be handled.',
            ]);
        }

        foreach ($records as $record) {
            // The record is NOT touched here. It comes off the board when an
            // admin approves the hide, which is also when the player is no
            // longer told one thing while q3df.org shows another.
            PlayerSelfReport::create([
                'user_id' => $user->id,
                'mdd_id' => $record->mdd_id,
                'player_name' => $record->name,
                'record_id' => $record->id,
                'mapname' => $record->mapname,
                'physics' => $record->physics,
                'mode' => $record->mode,
                'gametype' => $record->gametype,
                'time' => $record->time,
                'reason' => $data['reason'],
                'handling' => $data['handling'],
                'note' => $data['note'] ?? null,
            ]);
        }

        Log::info('Self-report requests filed', [
            'user_id' => $user->id,
            'mdd_id' => $user->mdd_id,
            'record_ids' => $records->pluck('id')->all(),
            'reason' => $data['reason'],
            'handling' => $data['handling'],
        ]);

        $count = $records->count();

        return back()->with('success', $count === 1
            ? 'Sent. The run stays on the board until an admin handles it, and nobody but the admin is told about it.'
            : $count . ' runs sent. They stay on the board until an admin handles them, and nobody but the admin is told about it.');
    }
}
