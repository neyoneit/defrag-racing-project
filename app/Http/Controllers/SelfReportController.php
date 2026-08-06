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
 * third one, and it only works while it stays free: no validator, no verdict,
 * no mark on the account. The time goes, and the log says who took it down
 * and why.
 *
 * It deletes the record on the spot for the same reason. A queue would mean
 * the honest answer still leaves the wrong time standing for a week, and the
 * player has already told us it does not belong there - there is nothing left
 * to establish.
 */
class SelfReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->input('search', ''));

        $records = collect();

        if ($user->mdd_id) {
            $records = Record::query()
                ->where('mdd_id', $user->mdd_id)
                ->when($search !== '', fn ($query) => $query->where('mapname', 'like', '%' . $search . '%'))
                ->orderByDesc('date_set')
                ->limit(60)
                ->get(['id', 'mapname', 'physics', 'mode', 'gametype', 'time', 'date_set', 'rank'])
                ->map(fn (Record $record) => [
                    'id' => $record->id,
                    'mapname' => $record->mapname,
                    'physics' => $record->physics,
                    'mode' => $record->mode,
                    'gametype' => $record->gametype,
                    'time' => $record->time,
                    'date_set' => $record->date_set,
                    'rank' => $record->rank,
                ]);
        }

        return Inertia::render('SelfReport', [
            'hasMddAccount' => (bool) $user->mdd_id,
            'records' => $records,
            'search' => $search,
            'totalRecords' => $user->mdd_id ? Record::where('mdd_id', $user->mdd_id)->count() : 0,
            'reasons' => RecordFlagController::FLAG_TYPES,
            'mine' => PlayerSelfReport::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get(['id', 'mapname', 'physics', 'mode', 'time', 'reason', 'created_at']),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'record_id' => ['required', 'integer', 'exists:records,id'],
            'reason' => ['required', 'string', 'in:' . implode(',', array_keys(RecordFlagController::FLAG_TYPES))],
            'note' => ['nullable', 'string', 'max:500'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Tick the box - the time is deleted straight away and cannot be put back by us.',
        ]);

        $record = Record::find($data['record_id']);

        // Yours means yours. The MDD id is what ties a record to a person, so
        // an account without one cannot withdraw anything, and an account with
        // a different one is somebody else's run.
        if (! $record || ! $user->mdd_id || (int) $record->mdd_id !== (int) $user->mdd_id) {
            return back()->withErrors([
                'record_id' => 'That run is not yours.',
            ]);
        }

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
            'note' => $data['note'] ?? null,
        ]);

        // Soft delete: the model's own hooks detach uploaded demos and clear
        // the profile and listing caches, which is exactly what has to happen
        // and is already tested by the admin-side deletions.
        $record->delete();

        Log::info('Self-reported record withdrawn', [
            'user_id' => $user->id,
            'mdd_id' => $record->mdd_id,
            'record_id' => $record->id,
            'mapname' => $record->mapname,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Time withdrawn. It is off the leaderboard and listed in the public validation log.');
    }
}
