<?php

namespace App\Http\Controllers;

use App\Models\AliasSuggestion;
use App\Models\User;
use App\Models\UserAlias;
use App\Notifications\AliasSuggestionReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AliasSuggestionController extends Controller
{
    /**
     * Store a new alias suggestion
     */
    public function store(Request $request, User $user)
    {
        // Prevent suggesting aliases to yourself
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot suggest aliases to yourself.');
        }

        $request->validate([
            'alias' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^^]+$/', // Disallow ^ character (Quake color codes)
            ],
            'note' => 'nullable|string|max:500',
        ], [
            'alias.regex' => 'Aliases cannot contain Quake 3 color codes (^).',
        ]);

        // Check if alias already exists globally
        if (UserAlias::where('alias', $request->alias)->exists()) {
            throw ValidationException::withMessages([
                'alias' => ['This alias is already taken by another user.'],
            ]);
        }

        // Check if there's already a pending suggestion for this alias to this user
        if (AliasSuggestion::where('user_id', $user->id)
            ->where('alias', $request->alias)
            ->where('status', 'pending')
            ->exists()) {
            throw ValidationException::withMessages([
                'alias' => ['This alias has already been suggested to this user.'],
            ]);
        }

        // Create suggestion
        $suggestion = AliasSuggestion::create([
            'user_id' => $user->id,
            'suggested_by_user_id' => Auth::id(),
            'alias' => $request->alias,
            'note' => $request->note,
            'status' => 'pending',
        ]);

        // Load the suggestedBy relationship for the notification
        $suggestion->load('suggestedBy');

        // Send notification to the user
        $user->notify(new AliasSuggestionReceived($suggestion));

        return back()->with('success', 'Alias suggestion sent successfully!');
    }

    /**
     * Approve an alias suggestion
     */
    public function approve(AliasSuggestion $suggestion)
    {
        // Ensure the suggestion belongs to the authenticated user
        if ($suggestion->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure suggestion is still pending
        if ($suggestion->status !== 'pending') {
            return back()->with('error', 'This suggestion has already been processed.');
        }

        // Check if user has reached alias limit
        if (Auth::user()->aliases()->count() >= 10) {
            return back()->with('error', 'Maximum 10 aliases allowed per account.');
        }

        // Check if alias is still available
        if (UserAlias::where('alias', $suggestion->alias)->exists()) {
            $suggestion->update(['status' => 'rejected']);
            return back()->with('error', 'This alias is no longer available.');
        }

        // Check if user is restricted (requires approval)
        $isApproved = !Auth::user()->alias_restricted;

        // Create the alias
        UserAlias::create([
            'user_id' => Auth::id(),
            'alias' => $suggestion->alias,
            'is_approved' => $isApproved,
        ]);

        // Update suggestion status
        $suggestion->update(['status' => 'approved']);

        $message = $isApproved
            ? 'Alias approved and added successfully! Demos will be rematched during the next scheduled run.'
            : 'Alias approved and submitted for admin approval.';

        return back()->with('success', $message);
    }

    /**
     * Reject an alias suggestion
     */
    public function reject(AliasSuggestion $suggestion)
    {
        // Ensure the suggestion belongs to the authenticated user
        if ($suggestion->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure suggestion is still pending
        if ($suggestion->status !== 'pending') {
            return back()->with('error', 'This suggestion has already been processed.');
        }

        $suggestion->update(['status' => 'rejected']);

        return back()->with('success', 'Alias suggestion rejected.');
    }
}
