<?php

namespace App\Http\Controllers;

use App\Models\UploadedDemo;
use App\Models\UserAlias;
use App\Jobs\RematchAliasDemosJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AliasController extends Controller
{
    /**
     * A user's alias set changes who their demos cluster under on every map's
     * Demos Top. That clustering is computed live but cached per map (keyed by
     * a `demostop_gen:<map>` generation counter, 1h TTL backstop), and the
     * auto-render queue reads a materialized `demos_top_ranks` table - neither
     * of which knows an alias changed. Without this, a newly added/approved
     * alias only takes visible effect after the cache happens to expire, which
     * looked like "my alias does nothing". This bumps the generation counter
     * (instant, cheap display invalidation) for every map the user has demos
     * on; the next view recomputes the cluster live and picks up the alias.
     * The heavier per-demo attribution + queue-table rebuild is handled by
     * RematchAliasDemosJob, scoped to the maps that actually carry the nick.
     */
    private function refreshDemosTopForUser(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        $maps = UploadedDemo::where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('suggested_user_id', $userId);
            })
            ->whereNotNull('map_name')
            ->distinct()
            ->pluck('map_name');

        foreach ($maps as $map) {
            Cache::increment('demostop_gen:' . $map);
        }
    }

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

        // Approved right away -> re-group this user's Demos Top now so the
        // alias takes visible effect immediately, not on the next cache miss.
        // (Pending aliases don't match anything until an admin approves them.)
        if ($isApproved) {
            $this->refreshDemosTopForUser($user->id);
            // Scan demos recorded under this nick and attribute them now
            // (suggested_user_id / matched_alias) instead of waiting for the
            // nightly rematch.
            RematchAliasDemosJob::dispatch($request->alias);
        }

        $message = $isApproved
            ? 'Alias added - your demos are being re-grouped under this nickname now.'
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

        $ownerId = $alias->user_id;
        $removedNick = $alias->alias;
        $alias->delete();

        // Removing an alias splits clusters back apart - re-group Demos Top
        // and re-attribute the demos that were recorded under that nick
        // against the remaining alias set.
        $this->refreshDemosTopForUser($ownerId);
        RematchAliasDemosJob::dispatch($removedNick);

        $message = 'Alias deleted successfully.';
        if ($unassignedCount > 0) {
            $message .= " {$unassignedCount} demo(s) will be rematched during the next scheduled run.";
        }

        return back()->with('success', $message);
    }
}
