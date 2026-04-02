<?php

namespace App\Http\Controllers;

use App\Models\AboutMeSubmission;
use App\Models\User;
use Illuminate\Http\Request;

class AboutMeController extends Controller
{
    public function submit(Request $request, $userId)
    {
        $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $author = $request->user();
        $profileUser = User::findOrFail($userId);

        // Prevent duplicate pending submissions
        $existing = AboutMeSubmission::where('user_id', $profileUser->id)
            ->where('submitted_by', $author->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            $existing->update(['content' => $request->content]);
            return back()->with('success', 'Your about me suggestion has been updated.');
        }

        $isSelf = $author->id === $profileUser->id;

        AboutMeSubmission::create([
            'user_id' => $profileUser->id,
            'submitted_by' => $author->id,
            'content' => $request->content,
            'type' => $profileUser->about_me ? 'edit' : 'create',
            'status' => 'pending',
        ]);

        return back()->with('success', $isSelf
            ? 'Your about me has been submitted for review.'
            : 'Your suggestion has been submitted for review.');
    }

    public function requestDelete(Request $request, $userId)
    {
        $author = $request->user();
        $profileUser = User::findOrFail($userId);

        // Only profile owner can request delete
        if ($author->id !== $profileUser->id) {
            abort(403);
        }

        if (!$profileUser->about_me) {
            return back()->with('danger', 'No about me to delete.');
        }

        $existing = AboutMeSubmission::where('user_id', $profileUser->id)
            ->where('submitted_by', $author->id)
            ->where('type', 'delete')
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return back()->with('danger', 'Delete request already pending.');
        }

        AboutMeSubmission::create([
            'user_id' => $profileUser->id,
            'submitted_by' => $author->id,
            'content' => null,
            'type' => 'delete',
            'status' => 'pending',
        ]);

        return back()->with('success', 'Delete request submitted for review.');
    }
}
