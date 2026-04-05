<?php

namespace App\Http\Controllers;

use App\Models\AliasSuggestion;
use App\Models\User;
use App\Models\UserAlias;
use App\Models\Notification;
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

        // Check if alias already exists for this user
        if ($user->mdd_id && UserAlias::where('mdd_id', $user->mdd_id)->where('alias', $request->alias)->exists()) {
            throw ValidationException::withMessages([
                'alias' => ['This alias already exists on this user\'s profile.'],
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

        // Send notification to the user
        Notification::create([
            'user_id' => $user->id,
            'type' => 'alias_suggestion',
            'before' => Auth::user()->name,
            'headline' => 'suggested you add the alias',
            'after' => $request->alias,
            'url' => route('profile.index', $user->id),
        ]);

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

        $user = Auth::user();

        // Check if alias already exists for this user's mdd_id
        if ($user->mdd_id && UserAlias::where('mdd_id', $user->mdd_id)->where('alias', $suggestion->alias)->exists()) {
            $suggestion->update(['status' => 'rejected']);
            return back()->with('error', 'This alias already exists on your profile.');
        }

        // Check if user is restricted (requires approval)
        $isApproved = !$user->alias_restricted;

        // Create the alias
        UserAlias::create([
            'user_id' => Auth::id(),
            'mdd_id' => $user->mdd_id,
            'alias' => $suggestion->alias,
            'source' => 'manual',
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
