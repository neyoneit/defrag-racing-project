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

        if (!$user->canReportDemos()) {
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

        // Check duplicate: same user, same target, same flag, within 30 days
        $duplicate = RecordFlag::where('flagged_by_user_id', $user->id)
            ->where('flag_type', $request->flag_type)
            ->where('created_at', '>', now()->subDays(30))
            ->where(function ($q) use ($request) {
                if ($request->record_id) {
                    $q->where('record_id', $request->record_id);
                }
                if ($request->demo_id) {
                    $q->where('demo_id', $request->demo_id);
                }
            })
            ->exists();

        if ($duplicate) {
            return back()->with('warning', 'You have already flagged this with the same flag recently.');
        }

        RecordFlag::create([
            'record_id' => $request->record_id,
            'demo_id' => $request->demo_id,
            'flag_type' => $request->flag_type,
            'flagged_by_user_id' => $user->id,
            'note' => $request->note,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Flag submitted for admin review.');
    }
}
