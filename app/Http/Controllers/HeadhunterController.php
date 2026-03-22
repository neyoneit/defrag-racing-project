<?php

namespace App\Http\Controllers;

use App\Models\HeadhunterChallenge;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeDispute;
use App\Models\Map;
use App\Models\OfflineRecord;
use App\Models\Record;
use App\Models\UploadedDemo;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HeadhunterController extends Controller
{
    public function index(Request $request)
    {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        if (!$isPartial) {
            return Inertia::render('Headhunter/Index', [
                'challenges' => null,
                'filters' => $request->only(['status', 'search']),
            ]);
        }

        $challenges = HeadhunterChallenge::with(['creator', 'participants.user', 'map:name,thumbnail'])
            ->withCount(['participants', 'approvedParticipants'])
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->search, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', "%{$request->search}%")
                      ->orWhere('mapname', 'like', "%{$request->search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return Inertia::render('Headhunter/Index', [
            'challenges' => $challenges,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(HeadhunterChallenge $challenge)
    {
        $challenge->load([
            'creator',
            'participants.user',
            'participants.record',
            'participants.offlineRecord',
        ]);

        $userParticipation = null;
        $userDemos = [];
        $hasActiveDispute = false;

        if (auth()->check()) {
            $userParticipation = $challenge->participants
                ->where('user_id', auth()->id())
                ->first();

            // Get user's eligible demos for proof submission dropdown
            if ($userParticipation && $userParticipation->canSubmit()) {
                // Online records (server-verified) - query by both user_id and mdd_id
                $onlineQuery = Record::where(function ($q) {
                        $q->where('user_id', auth()->id());
                        if (auth()->user()->mdd_id) {
                            $q->orWhere('mdd_id', auth()->user()->mdd_id);
                        }
                    })
                    ->where('mapname', $challenge->mapname)
                    ->where('physics', $challenge->physics)
                    ->where('time', '<=', $challenge->target_time);

                if ($challenge->mode !== 'any') {
                    $onlineQuery->where('mode', $challenge->mode);
                }

                $onlineRecords = $onlineQuery->orderBy('time')
                    ->get(['id', 'time', 'date_set', 'mode', 'name', 'rank'])
                    ->map(fn ($r) => array_merge($r->toArray(), ['source' => 'online']));

                // Offline records (from uploaded demos, not server-verified)
                $offlineGametypes = match ($challenge->mode) {
                    'run', 'strafe' => ['df'],
                    'freestyle' => ['fs'],
                    'fastcaps' => ['fc'],
                    'any' => ['df', 'fs', 'fc'],
                };

                $offlineRecords = OfflineRecord::whereHas('demo', function ($q) {
                        $q->where('user_id', auth()->id());
                    })
                    ->where('map_name', $challenge->mapname)
                    ->where('physics', $challenge->physics)
                    ->whereIn('gametype', $offlineGametypes)
                    ->where('time_ms', '<=', $challenge->target_time)
                    ->orderBy('time_ms')
                    ->get(['id', 'time_ms', 'date_set', 'gametype', 'player_name', 'rank'])
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'time' => $r->time_ms,
                        'date_set' => $r->date_set?->format('Y-m-d'),
                        'mode' => $r->gametype,
                        'name' => $r->player_name,
                        'rank' => $r->rank,
                        'source' => 'offline',
                    ]);

                $userDemos = $onlineRecords->concat($offlineRecords)
                    ->sortBy('time')
                    ->values()
                    ->all();
            }

            // Check if user has active dispute
            $hasActiveDispute = ChallengeDispute::where('challenge_id', $challenge->id)
                ->where('claimer_id', auth()->id())
                ->where('status', 'pending')
                ->exists();
        }

        // Current WR / top records for this map/physics/mode
        $currentRecordsQuery = Record::where('mapname', $challenge->mapname)
            ->where('physics', $challenge->physics);

        if ($challenge->mode !== 'any') {
            $currentRecordsQuery->where('mode', $challenge->mode);
        }

        $currentRecords = $currentRecordsQuery
            ->where('rank', 1)
            ->with('user:id,name,mdd_id,color,name_effect,avatar_effect,profile_photo_path,avatar_border_color')
            ->orderBy('time')
            ->limit(5)
            ->get(['id', 'time', 'date_set', 'name', 'user_id', 'mdd_id', 'mode']);

        // Map data with thumbnail
        $map = Map::where('name', $challenge->mapname)->first(['name', 'thumbnail']);

        // Grace period: editable within 1 hour of creation (for creator only)
        $isWithinGracePeriod = auth()->check()
            && $challenge->creator_id === auth()->id()
            && $challenge->created_at->addHour()->isFuture();

        // Creator stats
        $creatorStats = [
            'challenges_created' => HeadhunterChallenge::where('creator_id', $challenge->creator_id)->count(),
            'challenges_completed' => HeadhunterChallenge::where('creator_id', $challenge->creator_id)
                ->whereHas('approvedParticipants')
                ->count(),
            'total_approved' => ChallengeParticipant::whereHas('challenge', function ($q) use ($challenge) {
                    $q->where('creator_id', $challenge->creator_id);
                })
                ->where('status', 'approved')
                ->count(),
            'disputes_count' => ChallengeDispute::where('creator_id', $challenge->creator_id)->count(),
        ];

        // Load disputes for creator
        $disputes = [];
        if (auth()->check() && $challenge->creator_id === auth()->id()) {
            $disputes = ChallengeDispute::where('challenge_id', $challenge->id)
                ->with('claimer:id,name,color,name_effect,avatar_effect,profile_photo_path,avatar_border_color')
                ->latest()
                ->get()
                ->map(fn ($d) => array_merge($d->toArray(), [
                    'days_until_auto_ban' => $d->daysUntilAutoBan(),
                ]));
        }

        return Inertia::render('Headhunter/Show', [
            'challenge' => $challenge,
            'userParticipation' => $userParticipation,
            'userDemos' => $userDemos,
            'currentRecords' => $currentRecords,
            'map' => $map,
            'isWithinGracePeriod' => $isWithinGracePeriod,
            'hasActiveDispute' => $hasActiveDispute,
            'creatorStats' => $creatorStats,
            'disputes' => $disputes,
        ]);
    }

    public function create()
    {
        $userRecordsCount = Record::where('user_id', auth()->id())->count();

        if ($userRecordsCount < 30) {
            return redirect()->route('headhunter.index')
                ->withDanger("You need at least 30 records to create a challenge. You currently have {$userRecordsCount}.");
        }

        $isBanned = HeadhunterChallenge::where('creator_id', auth()->id())
            ->where('creator_banned', true)
            ->exists();

        if ($isBanned) {
            return redirect()->route('headhunter.index')
                ->withDanger('You are banned from creating Headhunter challenges.');
        }

        return Inertia::render('Headhunter/Create', [
            'maps' => Map::orderBy('name')->select('name', 'thumbnail')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $userRecordsCount = Record::where('user_id', auth()->id())->count();

        if ($userRecordsCount < 30) {
            return redirect()->back()
                ->withDanger("You need at least 30 records to create a challenge. You currently have {$userRecordsCount}.");
        }

        $isBanned = HeadhunterChallenge::where('creator_id', auth()->id())
            ->where('creator_banned', true)
            ->exists();

        if ($isBanned) {
            return redirect()->back()
                ->withDanger('You are banned from creating Headhunter challenges.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'mapname' => 'required|string|max:255|exists:maps,name',
            'physics' => 'required|in:vq3,cpm',
            'mode' => 'required|in:run,strafe,freestyle,fastcaps,any',
            'target_time' => 'required|integer|min:1',
            'reward_amount' => 'nullable|numeric|min:0',
            'reward_currency' => 'nullable|string|max:5',
            'reward_description' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $validated['creator_id'] = auth()->id();

        $challenge = HeadhunterChallenge::create($validated);

        return redirect()->route('headhunter.show', $challenge)
            ->withSuccess('Challenge created successfully!');
    }

    public function participate(HeadhunterChallenge $challenge)
    {
        if (!$challenge->canBeClaimed()) {
            return redirect()->back()->withDanger('This challenge cannot be participated in.');
        }

        $existing = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing) {
            return redirect()->back()->withDanger('You are already participating in this challenge.');
        }

        ChallengeParticipant::create([
            'challenge_id' => $challenge->id,
            'user_id' => auth()->id(),
            'status' => 'participating',
        ]);

        return redirect()->back()->withSuccess('You are now participating in this challenge!');
    }

    public function submitProof(Request $request, HeadhunterChallenge $challenge)
    {
        $participation = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (!$participation->canSubmit()) {
            return redirect()->back()->withDanger('You have already submitted proof for this challenge.');
        }

        $source = $request->input('source', 'online');

        if ($source === 'offline') {
            // Offline record submission
            $validated = $request->validate([
                'offline_record_id' => 'required|exists:offline_records,id',
                'submission_notes' => 'nullable|string|max:1000',
            ]);

            $offlineRecord = OfflineRecord::with('demo')->findOrFail($validated['offline_record_id']);

            // Verify the offline record belongs to the user (through uploaded demo)
            if (!$offlineRecord->demo || $offlineRecord->demo->user_id !== auth()->id()) {
                return redirect()->back()->withDanger('This record does not belong to you.');
            }

            if ($offlineRecord->map_name !== $challenge->mapname) {
                return redirect()->back()->withDanger('This record is not for the correct map.');
            }

            if (strtolower($offlineRecord->physics) !== $challenge->physics) {
                return redirect()->back()->withDanger('This record is not for the correct physics mode.');
            }

            if ($offlineRecord->time_ms > $challenge->target_time) {
                return redirect()->back()->withDanger('Your time does not beat the challenge target time.');
            }

            $participation->update([
                'offline_record_id' => $offlineRecord->id,
                'record_id' => null,
                'submission_notes' => $validated['submission_notes'] ?? null,
                'submitted_at' => now(),
                'status' => 'submitted',
                'rejection_reason' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
            ]);
        } else {
            // Online record submission
            $validated = $request->validate([
                'record_id' => 'required|exists:records,id',
                'submission_notes' => 'nullable|string|max:1000',
            ]);

            $record = Record::findOrFail($validated['record_id']);

            // Verify ownership via user_id or mdd_id
            $isOwner = $record->user_id === auth()->id();
            if (!$isOwner && auth()->user()->mdd_id && $record->mdd_id === auth()->user()->mdd_id) {
                $isOwner = true;
            }

            if (!$isOwner) {
                return redirect()->back()->withDanger('This record does not belong to you.');
            }

            if ($record->mapname !== $challenge->mapname) {
                return redirect()->back()->withDanger('This record is not for the correct map.');
            }

            if ($record->physics !== $challenge->physics) {
                return redirect()->back()->withDanger('This record is not for the correct physics mode.');
            }

            if ($challenge->mode !== 'any' && $record->mode !== $challenge->mode) {
                return redirect()->back()->withDanger('This record is not for the correct game mode.');
            }

            if ($record->time > $challenge->target_time) {
                return redirect()->back()->withDanger('Your time does not beat the challenge target time.');
            }

            $participation->update([
                'record_id' => $record->id,
                'offline_record_id' => null,
                'submission_notes' => $validated['submission_notes'] ?? null,
                'submitted_at' => now(),
                'status' => 'submitted',
                'rejection_reason' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
            ]);
        }

        $challenge->creator->systemNotify(
            'headhunter_submission',
            'New submission for your challenge',
            auth()->user()->name . ' has submitted proof for challenge "' . $challenge->title . '".',
            '',
            route('headhunter.show', $challenge)
        );

        return redirect()->back()->withSuccess('Proof submitted successfully! Waiting for creator review.');
    }

    public function approveSubmission(HeadhunterChallenge $challenge, ChallengeParticipant $participant)
    {
        if ($challenge->creator_id !== auth()->id()) {
            return redirect()->back()->withDanger('Only the challenge creator can approve submissions.');
        }

        if ($participant->challenge_id !== $challenge->id) {
            return redirect()->back()->withDanger('This participant does not belong to this challenge.');
        }

        if ($participant->status !== 'submitted') {
            return redirect()->back()->withDanger('This submission cannot be approved.');
        }

        $participant->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by_user_id' => auth()->id(),
        ]);

        $participant->user->systemNotify(
            'headhunter_approved',
            'Your submission was approved!',
            'Your proof for challenge "' . $challenge->title . '" has been approved by the creator.',
            '',
            route('headhunter.show', $challenge)
        );

        return redirect()->back()->withSuccess('Submission approved successfully!');
    }

    public function rejectSubmission(Request $request, HeadhunterChallenge $challenge, ChallengeParticipant $participant)
    {
        if ($challenge->creator_id !== auth()->id()) {
            return redirect()->back()->withDanger('Only the challenge creator can reject submissions.');
        }

        if ($participant->challenge_id !== $challenge->id) {
            return redirect()->back()->withDanger('This participant does not belong to this challenge.');
        }

        if ($participant->status !== 'submitted') {
            return redirect()->back()->withDanger('This submission cannot be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $participant->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_at' => now(),
            'reviewed_by_user_id' => auth()->id(),
        ]);

        $participant->user->systemNotify(
            'headhunter_rejected',
            'Your submission was rejected',
            'Your proof for challenge "' . $challenge->title . '" was rejected: ' . $validated['rejection_reason'],
            '',
            route('headhunter.show', $challenge)
        );

        return redirect()->back()->withSuccess('Submission rejected.');
    }

    public function closeChallenge(HeadhunterChallenge $challenge)
    {
        if ($challenge->creator_id !== auth()->id()) {
            return redirect()->back()->withDanger('Only the challenge creator can close this challenge.');
        }

        if ($challenge->status === 'closed') {
            return redirect()->back()->withDanger('This challenge is already closed.');
        }

        $challenge->update(['status' => 'closed']);

        $challenge->load('participants.user');
        foreach ($challenge->participants as $participant) {
            if ($participant->user) {
                $participant->user->systemNotify(
                    'headhunter_closed',
                    'Challenge closed',
                    'The challenge "' . $challenge->title . '" has been closed by the creator.',
                    '',
                    route('headhunter.show', $challenge)
                );
            }
        }

        return redirect()->back()->withSuccess('Challenge closed.');
    }

    public function updateChallenge(Request $request, HeadhunterChallenge $challenge)
    {
        if ($challenge->creator_id !== auth()->id()) {
            return redirect()->back()->withDanger('Only the challenge creator can edit this challenge.');
        }

        if ($challenge->created_at->addHour()->isPast()) {
            return redirect()->back()->withDanger('The 1-hour grace period for editing has expired. Use "Request Edit" instead.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'mapname' => 'required|string|max:255|exists:maps,name',
            'physics' => 'required|in:vq3,cpm',
            'mode' => 'required|in:run,strafe,freestyle,fastcaps,any',
            'target_time' => 'required|integer|min:1',
            'reward_amount' => 'nullable|numeric|min:0',
            'reward_currency' => 'nullable|string|max:5',
            'reward_description' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $challenge->update($validated);

        return redirect()->back()->withSuccess('Challenge updated successfully!');
    }

    public function requestEdit(Request $request, HeadhunterChallenge $challenge)
    {
        if ($challenge->creator_id !== auth()->id()) {
            return redirect()->back()->withDanger('Only the challenge creator can request edits.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $admins = User::where('admin', true)->get();
        foreach ($admins as $admin) {
            $admin->systemNotify(
                'headhunter_edit_request',
                'Challenge edit request',
                auth()->user()->name . ' requests edit for "' . $challenge->title . '": ' . $validated['reason'],
                '',
                route('headhunter.show', $challenge)
            );
        }

        return redirect()->back()->withSuccess('Edit request sent to administrators.');
    }

    public function createDispute(Request $request, HeadhunterChallenge $challenge)
    {
        $participation = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('user_id', auth()->id())
            ->where('status', 'approved')
            ->first();

        if (!$participation) {
            return redirect()->back()->withDanger('Only approved participants can file a dispute.');
        }

        $existingDispute = ChallengeDispute::where('challenge_id', $challenge->id)
            ->where('claimer_id', auth()->id())
            ->where('status', 'pending')
            ->first();

        if ($existingDispute) {
            return redirect()->back()->withDanger('You already have a pending dispute for this challenge.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
            'evidence' => 'nullable|string|max:2000',
        ]);

        ChallengeDispute::create([
            'challenge_id' => $challenge->id,
            'claimer_id' => auth()->id(),
            'creator_id' => $challenge->creator_id,
            'reason' => $validated['reason'],
            'evidence' => $validated['evidence'] ?? null,
            'status' => 'pending',
        ]);

        $challenge->update(['status' => 'disputed']);

        $challenge->creator->systemNotify(
            'headhunter_dispute',
            'Dispute filed on your challenge',
            auth()->user()->name . ' has filed a dispute on "' . $challenge->title . '". You have 14 days to respond.',
            '',
            route('headhunter.show', $challenge)
        );

        return redirect()->back()->withSuccess('Dispute filed. The creator has 14 days to respond.');
    }

    public function unapproveSubmission(HeadhunterChallenge $challenge, ChallengeParticipant $participant)
    {
        if ($challenge->creator_id !== auth()->id()) {
            return redirect()->back()->withDanger('Only the challenge creator can modify submissions.');
        }

        if ($participant->challenge_id !== $challenge->id) {
            return redirect()->back()->withDanger('This participant does not belong to this challenge.');
        }

        if ($participant->status !== 'approved') {
            return redirect()->back()->withDanger('This submission is not currently approved.');
        }

        $participant->update([
            'status' => 'submitted',
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
        ]);

        $participant->user->systemNotify(
            'headhunter_unapproved',
            'Your approval was reverted',
            'Your approved submission for challenge "' . $challenge->title . '" has been reverted to pending review.',
            '',
            route('headhunter.show', $challenge)
        );

        return redirect()->back()->withSuccess('Approval reverted. Submission is back to pending review.');
    }

    public function removeParticipant(HeadhunterChallenge $challenge, ChallengeParticipant $participant)
    {
        if ($challenge->creator_id !== auth()->id()) {
            return redirect()->back()->withDanger('Only the challenge creator can remove participants.');
        }

        if ($participant->challenge_id !== $challenge->id) {
            return redirect()->back()->withDanger('This participant does not belong to this challenge.');
        }

        $participantUser = $participant->user;

        $participant->delete();

        if ($participantUser) {
            $participantUser->systemNotify(
                'headhunter_removed',
                'You were removed from a challenge',
                'You have been removed from challenge "' . $challenge->title . '" by the creator.',
                '',
                route('headhunter.show', $challenge)
            );
        }

        return redirect()->back()->withSuccess('Participant removed.');
    }

    public function respondToDispute(Request $request, HeadhunterChallenge $challenge, ChallengeDispute $dispute)
    {
        if ($challenge->creator_id !== auth()->id()) {
            return redirect()->back()->withDanger('Only the challenge creator can respond to disputes.');
        }

        if ($dispute->challenge_id !== $challenge->id) {
            return redirect()->back()->withDanger('This dispute does not belong to this challenge.');
        }

        if ($dispute->creator_response) {
            return redirect()->back()->withDanger('You have already responded to this dispute.');
        }

        $validated = $request->validate([
            'creator_response' => 'required|string|max:2000',
        ]);

        $dispute->update([
            'creator_response' => $validated['creator_response'],
            'creator_responded_at' => now(),
            'status' => 'responded',
        ]);

        // Notify the claimer
        $dispute->claimer->systemNotify(
            'headhunter_dispute_response',
            'Creator responded to your dispute',
            'The creator of "' . $challenge->title . '" has responded to your dispute. An admin will review.',
            '',
            route('headhunter.show', $challenge)
        );

        // Notify admins
        $admins = User::where('admin', true)->get();
        foreach ($admins as $admin) {
            $admin->systemNotify(
                'headhunter_dispute_response',
                'Dispute response requires review',
                'Creator responded to a dispute on "' . $challenge->title . '". Both sides have been heard.',
                '',
                route('headhunter.show', $challenge)
            );
        }

        return redirect()->back()->withSuccess('Response submitted. An administrator will review the dispute.');
    }
}
