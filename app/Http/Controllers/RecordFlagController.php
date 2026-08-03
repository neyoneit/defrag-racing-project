<?php

namespace App\Http\Controllers;

use App\Models\RecordFlag;
use App\Models\Record;
use App\Models\UploadedDemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecordFlagController extends Controller
{
    const FLAG_TYPES = [
        'sv_cheats' => 'sv_cheats enabled',
        'tool_assisted' => 'Tool-assisted speedrun (TAS)',
        'client_finish' => 'No proper finish (client_finish=false)',
        'timescale' => 'Timescale modified',
        'g_speed' => 'Movement speed modified (g_speed)',
        'g_gravity' => 'Gravity modified (g_gravity)',
        'sv_fps' => 'Non-standard server FPS (sv_fps)',
        'com_maxfps' => 'Non-standard max FPS (com_maxfps)',
        'pmove_fixed' => 'Non-standard pmove_fixed',
        'pmove_msec' => 'Non-standard pmove_msec',
        'df_mp_interferenceoff' => 'Interference setting modified',
        'other' => 'Other validity issue',
    ];

    public function store(Request $request)
    {
        $user = Auth::user();

        // Flagging a DEMO keeps the 30-record bar: it is a claim about
        // somebody's upload and carries weight. Flagging a RECORD does not -
        // it only says a time looks wrong, and what evidence exists behind it
        // is something only staff can see anyway. Anyone with an account may
        // raise one.
        if ($request->demo_id && !$user->canReportDemos()) {
            return back()->with('danger', 'You need at least 30 records to flag demos.');
        }

        $request->validate([
            'record_id' => 'nullable|exists:records,id',
            'demo_id' => 'nullable|exists:uploaded_demos,id',
            'flag_type' => 'required|in:' . implode(',', array_keys(self::FLAG_TYPES)),
            'note' => 'nullable|string|max:500',
        ]);

        if (!$request->record_id && !$request->demo_id) {
            return back()->with('danger', 'No record or demo specified.');
        }

        // Find existing flag with same type on same target (pending or approved)
        $existing = RecordFlag::where('flag_type', $request->flag_type)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($request) {
                if ($request->record_id) {
                    $q->where('record_id', $request->record_id);
                } else {
                    $q->whereNull('record_id');
                }
                if ($request->demo_id) {
                    $q->where('demo_id', $request->demo_id);
                } else {
                    $q->whereNull('demo_id');
                }
            })
            ->first();

        if ($existing) {
            $users = $existing->flagged_by_users ?? [$existing->flagged_by_user_id];

            // Check if this user already flagged
            if (in_array($user->id, $users)) {
                return back()->with('warning', 'You have already flagged this with the same flag.');
            }

            // Accumulate
            $users[] = $user->id;
            $notes = $existing->note;
            if ($request->note) {
                $notes = ($notes ? $notes . "\n" : '') . "[{$user->name}] {$request->note}";
            }

            $existing->update([
                'flagged_by_users' => $users,
                'flag_count' => count($users),
                'note' => $notes,
            ]);

            // A report the admin already cleared but that was short of the
            // reporter threshold joins its case the moment it reaches it -
            // the two conditions can be met in either order.
            if ($existing->isReadyForValidators() && $existing->validation_case_id === null) {
                app(\App\Services\ServerdemoValidationService::class)->attachToCase($existing);
            }

            return back()->with('success', 'Flag added. ' . count($users) . ' users have flagged this.');
        }

        // New flag
        RecordFlag::create([
            'record_id' => $request->record_id,
            'demo_id' => $request->demo_id,
            'flag_type' => $request->flag_type,
            'flagged_by_user_id' => $user->id,
            'flagged_by_users' => [$user->id],
            'flag_count' => 1,
            'note' => $request->note,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Flag submitted for admin review.');
    }
}
