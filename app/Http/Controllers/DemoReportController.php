<?php

namespace App\Http\Controllers;

use App\Models\UploadedDemo;
use App\Models\DemoAssignmentReport;
use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoReportController extends Controller
{
    /**
     * Predefined report reasons
     */
    const REASSIGNMENT_REASONS = [
        'wrong_player' => 'Wrong player assigned',
        'better_match' => 'Better match found',
        'time_mismatch' => 'Time/physics mismatch',
        'other' => 'Other',
    ];

    const WRONG_ASSIGNMENT_REASONS = [
        'wrong_player' => 'Wrong player - name doesn\'t match',
        'wrong_map' => 'Wrong map',
        'wrong_time' => 'Wrong time',
        'duplicate' => 'Duplicate demo',
        'cheated' => 'Cheated/Modified demo',
        'other' => 'Other',
    ];

    const BAD_DEMO_REASONS = [
        'corrupted' => 'Corrupted demo file',
        'fake' => 'Fake/modified demo',
        'spam' => 'Spam upload',
        'inappropriate' => 'Inappropriate content',
        'duplicate' => 'Duplicate of existing demo',
        'other' => 'Other',
    ];

    /**
     * Submit a demo report
     */
    public function store(Request $request, UploadedDemo $demo)
    {
        $user = Auth::user();

        // Check if user meets requirements (30 records minimum)
        if (!$user->canReportDemos()) {
            return back()->with('danger', 'You need at least 30 records to report demos.');
        }

        $request->validate([
            'report_type' => 'required|in:reassignment_request,wrong_assignment,bad_demo',
            'reason_type' => 'required|string',
            'reason_details' => 'nullable|string|max:1000',
            'suggested_record_id' => 'nullable|exists:records,id',
        ]);

        // For reassignment requests, check if user can assign demos
        if ($request->report_type === 'reassignment_request') {
            if (!$user->canAssignDemos()) {
                return back()->with('danger', 'You need at least 30 records and cannot be restricted to request reassignments.');
            }

            if (!$request->suggested_record_id) {
                return back()->with('danger', 'Please select a record to assign this demo to.');
            }
        }

        // Check if user already reported this demo recently
        $recentReport = DemoAssignmentReport::where('demo_id', $demo->id)
            ->where('reported_by_user_id', $user->id)
            ->where('created_at', '>', now()->subDays(7))
            ->exists();

        if ($recentReport) {
            return back()->with('warning', 'You have already reported this demo recently.');
        }

        // Create the report
        $report = DemoAssignmentReport::create([
            'demo_id' => $demo->id,
            'report_type' => $request->report_type,
            'reported_by_user_id' => $user->id,
            'current_record_id' => $demo->record_id,
            'suggested_record_id' => $request->suggested_record_id,
            'reason_type' => $request->reason_type,
            'reason_details' => $request->reason_details,
            'status' => 'pending',
        ]);

        $messages = [
            'reassignment_request' => 'Reassignment request submitted for admin review.',
            'wrong_assignment' => 'Wrong assignment reported. Admins will investigate.',
            'bad_demo' => 'Demo reported. Admins will review it.',
        ];

        return back()->with('success', $messages[$request->report_type]);
    }
}
