<?php

namespace App\Http\Controllers;

use App\Models\UserAlias;
use App\Jobs\RematchDemosByAlias;
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
            'alias' => 'required|string|max:255|unique:user_aliases,alias',
        ]);

        $user = Auth::user();

        // Check if user has reached alias limit
        if ($user->aliases()->count() >= 10) {
            throw ValidationException::withMessages([
                'alias' => ['Maximum 10 aliases allowed per account.'],
            ]);
        }

        // Check if user is restricted
        $isApproved = !$user->alias_restricted;

        // Create alias
        $alias = UserAlias::create([
            'user_id' => $user->id,
            'alias' => $request->alias,
            'is_approved' => $isApproved,
        ]);

        // Trigger retroactive demo matching if alias is approved
        if ($isApproved) {
            dispatch(new RematchDemosByAlias($alias));
        }

        $message = $isApproved
            ? 'Alias added successfully! Checking existing demos for matches...'
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

        $alias->delete();

        return back()->with('success', 'Alias deleted successfully.');
    }
}
