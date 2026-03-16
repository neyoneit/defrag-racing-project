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

    const FALSE_FLAG_REASONS = [
        'legitimate' => 'Record is legitimate - flag is incorrect',
        'wrong_flag' => 'Wrong flag type was applied',
        'resolved' => 'Issue was already resolved',
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
            'report_type' => 'required|in:wrong_assignment,bad_demo,false_flag',
            'reason_type' => 'required|string',
            'reason_details' => 'nullable|string|max:1000',
            'suggested_record_id' => 'nullable|exists:records,id',
        ]);

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
            'wrong_assignment' => 'Wrong assignment reported. Admins will investigate.',
            'bad_demo' => 'Demo reported. Admins will review it.',
            'false_flag' => 'False flag reported. Admins will review it.',
        ];

        return back()->with('success', $messages[$request->report_type]);
    }
}
