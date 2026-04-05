<?php

namespace App\Http\Controllers;

use App\Models\UserAlias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AliasController extends Controller
{
    /**
     * Store a new alias
     */
    public function store(Request $request)
    {
        $request->validate([
            'alias' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^^]+$/', // Disallow ^ character (Quake color codes)
            ],
        ], [
            'alias.regex' => 'Aliases cannot contain Quake 3 color codes (^).',
        ]);

        $user = Auth::user();

        // Check if alias already exists for this user
        $exists = $user->mdd_id
            ? UserAlias::where('mdd_id', $user->mdd_id)->where('alias', $request->alias)->exists()
            : UserAlias::where('user_id', $user->id)->where('alias', $request->alias)->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'alias' => ['This alias already exists on your profile.'],
            ]);
        }

        // Check if user is restricted
        $isApproved = !$user->alias_restricted;

        // Create alias
        $alias = UserAlias::create([
            'user_id' => $user->id,
            'mdd_id' => $user->mdd_id,
            'alias' => $request->alias,
            'source' => 'manual',
            'is_approved' => $isApproved,
        ]);

        $message = $isApproved
            ? 'Alias added successfully! Demos will be rematched during the next scheduled run.'
            : 'Alias submitted for admin approval.';

        return back()->with('success', $message);
    }

    /**
     * Delete an alias
     */
    public function destroy(UserAlias $alias)
    {
        // Ensure the alias belongs to the authenticated user
        if ($alias->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Find all demos that were specifically matched using this alias
        $affectedDemos = \App\Models\UploadedDemo::where('status', 'assigned')
            ->where('matched_alias', $alias->alias)
            ->whereHas('record', function ($query) use ($alias) {
                $query->where('user_id', $alias->user_id);
            })
            ->get();

        // Unassign these demos so they can be rematched with other aliases or primary name
        $unassignedCount = 0;
        foreach ($affectedDemos as $demo) {
            $demo->update([
                'record_id' => null,
                'status' => 'processed',
                'matched_alias' => null,
            ]);
            $unassignedCount++;
        }

        $alias->delete();

        $message = 'Alias deleted successfully.';
        if ($unassignedCount > 0) {
            $message .= " {$unassignedCount} demo(s) will be rematched during the next scheduled run.";
        }

        return back()->with('success', $message);
    }
}
