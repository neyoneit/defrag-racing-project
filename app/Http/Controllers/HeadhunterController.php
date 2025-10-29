<?php

namespace App\Http\Controllers;

use App\Models\HeadhunterChallenge;
use App\Models\ChallengeParticipant;
use App\Models\Record;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HeadhunterController extends Controller
{
    public function index(Request $request)
    {
        $challenges = HeadhunterChallenge::with(['creator', 'participants'])
            ->withCount(['participants', 'approvedParticipants'])
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->search, function ($query) use ($request) {
                return $query->where('title', 'like', "%{$request->search}%")
                    ->orWhere('mapname', 'like', "%{$request->search}%");
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
            'participants' => function ($query) {
                $query->where('status', 'approved')->with('record');
            }
        ]);

        $userParticipation = null;
        if (auth()->check()) {
            $userParticipation = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('user_id', auth()->id())
                ->first();
        }

        return Inertia::render('Headhunter/Show', [
            'challenge' => $challenge,
            'userParticipation' => $userParticipation,
        ]);
    }

    public function create()
    {
        // Check if user has at least 30 records
        $userRecordsCount = Record::where('user_id', auth()->id())->count();

        if ($userRecordsCount < 30) {
            return redirect()->route('headhunter.index')
                ->withDanger("You need at least 30 records to create a challenge. You currently have {$userRecordsCount}.");
        }

        // Check if user is banned
        $isBanned = HeadhunterChallenge::where('creator_id', auth()->id())
            ->where('creator_banned', true)
            ->exists();

        if ($isBanned) {
            return redirect()->route('headhunter.index')
                ->withDanger('You are banned from creating Headhunter challenges.');
        }

        return Inertia::render('Headhunter/Create');
    }

    public function store(Request $request)
    {
        // Check if user has at least 30 records
        $userRecordsCount = Record::where('user_id', auth()->id())->count();

        if ($userRecordsCount < 30) {
            return redirect()->back()
                ->withDanger("You need at least 30 records to create a challenge. You currently have {$userRecordsCount}.");
        }

        // Check if user is banned
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
            'reward_currency' => 'nullable|string|size:3',
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

        if ($challenge->creator_id === auth()->id()) {
            return redirect()->back()->withDanger('You cannot participate in your own challenge.');
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

        $validated = $request->validate([
            'record_id' => 'required|exists:records,id',
            'submission_notes' => 'nullable|string|max:1000',
        ]);

        // Verify the record belongs to the user and matches the challenge requirements
        $record = Record::findOrFail($validated['record_id']);

        if ($record->user_id !== auth()->id()) {
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

        $validated['submitted_at'] = now();
        $validated['status'] = 'submitted';

        $participation->update($validated);

        // Notify challenge creator
        $challenge->creator->systemNotify(
            'headhunter_submission',
            'New submission for your challenge',
            auth()->user()->name . ' has submitted proof for challenge "' . $challenge->title . '".',
            route('headhunter.show', $challenge)
        );

        return redirect()->back()->withSuccess('Proof submitted successfully! Waiting for creator review.');
    }
}
