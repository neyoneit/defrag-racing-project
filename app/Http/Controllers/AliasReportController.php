<?php

namespace App\Http\Controllers;

use App\Models\UserAlias;
use App\Models\AliasReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AliasReportController extends Controller
{
    /**
     * Report an alias as false/incorrect
     */
    public function store(Request $request, UserAlias $alias)
    {
        $user = Auth::user();

        // Check if user meets requirements
        if (!$user->canReportDemos()) {
            return back()->with('danger', 'You need at least 30 records to report aliases.');
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Check if user already reported this alias
        $existing = AliasReport::where('alias_id', $alias->id)
            ->where('reported_by_user_id', $user->id)
            ->exists();

        if ($existing) {
            return back()->with('warning', 'You have already reported this alias.');
        }

        AliasReport::create([
            'alias_id' => $alias->id,
            'reported_by_user_id' => $user->id,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Alias reported. Admins will review it.');
    }
}
