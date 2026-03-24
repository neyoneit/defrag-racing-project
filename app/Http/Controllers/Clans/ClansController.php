<?php

namespace App\Http\Controllers\Clans;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Clan;
use App\Models\User;
use App\Models\ClanInvitation;
use App\Models\ClanPlayer;
use App\Models\ClanBlockedUser;

use App\Http\Controllers\Controller;

use Carbon\Carbon;

class ClansController extends Controller {
    public function index (Request $request) {
        $sortBy = $request->get('sort', 'wrs');
        $sortDir = $request->get('dir', 'desc');

        if ($request->user()) {
            $myClan = $request->user()
                ->clan()
                ->with('admin:id,name')
                ->with(['players.user' => function ($query) {
                    $query->select('id', 'name', 'profile_photo_path', 'country', 'plain_name');
                }])
                ->withCount('players')
                ->first();


            $invitations = ClanInvitation::query()
                ->where('user_id', $request->user()->id)
                ->where('accepted', false)
                ->where('type', 'invite')
                ->has('clan')
                ->with('clan')
                ->get();

            // Get pending join requests for clan admin
            $joinRequests = [];
            $blockedUsers = [];
            if ($myClan && $myClan->admin_id === $request->user()->id) {
                $joinRequests = ClanInvitation::query()
                    ->where('clan_id', $myClan->id)
                    ->where('type', 'request')
                    ->where('accepted', false)
                    ->with(['user:id,name,country,plain_name,profile_photo_path'])
                    ->get();

                $blockedUsers = ClanBlockedUser::query()
                    ->where('clan_id', $myClan->id)
                    ->with(['user:id,name,country,plain_name,profile_photo_path'])
                    ->get();
            }

            $users = User::query()->orderBy('plain_name')->get(['id', 'name', 'country', 'plain_name']);
        } else {
            $myClan = null;
            $users = [];
            $invitations = [];
            $joinRequests = [];
            $blockedUsers = [];
        }

        return Inertia::render('Clans/Index')
            ->with('clans', Inertia::lazy(function () use ($sortBy, $sortDir) {
                $query = Clan::with('admin:id,name')
                    ->with(['players.user' => function ($q) {
                        $q->select('id', 'name', 'profile_photo_path', 'cached_wr_count', 'cached_top3_count');
                    }])
                    ->withCount('players');

                $query->selectRaw('clans.*,
                    (SELECT COUNT(DISTINCT records.id)
                     FROM clan_players
                     JOIN records ON clan_players.user_id = records.user_id
                     WHERE clan_players.clan_id = clans.id AND clan_players.deleted_at IS NULL AND records.deleted_at IS NULL) as total_records,
                    (SELECT COALESCE(SUM(users.cached_wr_count), 0)
                     FROM clan_players
                     JOIN users ON clan_players.user_id = users.id
                     WHERE clan_players.clan_id = clans.id AND clan_players.deleted_at IS NULL) as total_wrs,
                    (SELECT COALESCE(SUM(users.cached_top3_count), 0)
                     FROM clan_players
                     JOIN users ON clan_players.user_id = users.id
                     WHERE clan_players.clan_id = clans.id AND clan_players.deleted_at IS NULL) as total_top3
                ');

                switch ($sortBy) {
                    case 'members':
                        $query->orderBy('players_count', $sortDir);
                        break;
                    case 'wrs':
                        $query->orderByRaw("total_wrs {$sortDir}");
                        break;
                    case 'top3':
                        $query->orderByRaw("total_top3 {$sortDir}");
                        break;
                    case 'name':
                    default:
                        $query->orderBy('name', $sortDir);
                        break;
                }

                return $query->paginate(20)->appends(['sort' => $sortBy, 'dir' => $sortDir]);
            }))
            ->with('myClan', $myClan)
            ->with('users', $users)
            ->with('invitations', $invitations)
            ->with('joinRequests', $joinRequests)
            ->with('blockedUsers', $blockedUsers)
            ->with('currentSort', $sortBy)
            ->with('currentDir', $sortDir);
    }

    public function show(Clan $clan, Request $request) {
        $clan->load(['players.user' => function ($query) {
            $query->select('id', 'name', 'profile_photo_path', 'country', 'plain_name');
        }]);

        // Transform the data to match the expected structure
        $players = $clan->players->map(function ($clanPlayer) {
            $user = $clanPlayer->user;
            $user->pivot = (object)[
                'note' => $clanPlayer->note,
                'position' => $clanPlayer->position,
                'config_file' => $clanPlayer->config_file
            ];
            return $user;
        });

        // Get clan statistics
        $statisticsController = new \App\Http\Controllers\Clans\ClanStatisticsController();
        $statistics = $statisticsController->getStatistics($clan);

        // Check if current user has a pending join request or is blocked
        $userRequestStatus = null;
        $userIsMember = false;
        $userIsInClan = false;
        if ($request->user()) {
            $userId = $request->user()->id;
            $userIsMember = $clan->players->where('user_id', $userId)->isNotEmpty();
            $userIsInClan = ClanPlayer::where('user_id', $userId)->exists();

            if (!$userIsMember) {
                $pendingRequest = ClanInvitation::where('clan_id', $clan->id)
                    ->where('user_id', $userId)
                    ->where('type', 'request')
                    ->where('accepted', false)
                    ->first();

                if ($pendingRequest) {
                    $userRequestStatus = 'pending';
                } elseif (ClanBlockedUser::where('clan_id', $clan->id)->where('user_id', $userId)->exists()) {
                    $userRequestStatus = 'blocked';
                }
            }
        }

        return Inertia::render('Clans/Show')
            ->with('clan', $clan)
            ->with('players', $players)
            ->with('statistics', $statistics)
            ->with('userRequestStatus', $userRequestStatus)
            ->with('userIsMember', $userIsMember)
            ->with('userIsInClan', $userIsInClan)
            ->with('pendingRequestId', isset($pendingRequest) ? $pendingRequest->id : null);
    }

    public function accept(ClanInvitation $invitation, Request $request) {
        if ($invitation->user_id !== $request->user()->id) {
            return redirect()->route('clans.index')->withDanger('You are not allowed to do that');
        }

        if ($request->user()->clan()->first() && $request->user()->clan()->first()->admin_id === $request->user()->id) {
            return redirect()->route('clans.index')->withDanger('You are the admin of another clan, you need to either transfer ownership or dismantle your current clan before joining another one.');
        }

        $invitation->accepted = true;
        $invitation->save();

        $clanPlayers = ClanPlayer::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        $clanPlayer = new ClanPlayer();
        $clanPlayer->clan_id = $invitation->clan_id;
        $clanPlayer->user_id = $request->user()->id;

        $clanPlayer->save();

        $invitation->clan->admin->systemNotify('clan_accept', 'The player ', $request->user()->name, ' Has Accepted the clan invitation, and is now a member of your clan.', route('profile.index', $request->user()));

        return redirect()->route('clans.index')->withSuccess('You have joined the clan');
    }

    public function reject(ClanInvitation $invitation, Request $request) {
        if ($invitation->user_id !== $request->user()->id) {
            return redirect()->route('clans.index')->withDanger('You are not allowed to do that');
        }

        $invitation->delete();

        return redirect()->route('clans.index')->withSuccess('You have rejected the invitation');
    }

    public function requestJoin(Clan $clan, Request $request) {
        $user = $request->user();

        // Check if user is already in a clan
        if ($user->clan()->exists()) {
            return redirect()->back()->withDanger('You are already in a clan. Leave your current clan first.');
        }

        // Check if clan is hidden or banned
        if ($clan->hidden || $clan->banned) {
            return redirect()->back()->withDanger('This clan is not accepting requests.');
        }

        // Check if user is blocked by this clan
        if (ClanBlockedUser::where('clan_id', $clan->id)->where('user_id', $user->id)->exists()) {
            return redirect()->back()->withDanger('You are not allowed to request to join this clan.');
        }

        // Check if there's already a pending request
        if (ClanInvitation::where('clan_id', $clan->id)->where('user_id', $user->id)->where('type', 'request')->where('accepted', false)->exists()) {
            return redirect()->back()->withDanger('You already have a pending request to this clan.');
        }

        // Check if there's already a pending invite
        if (ClanInvitation::where('clan_id', $clan->id)->where('user_id', $user->id)->where('type', 'invite')->where('accepted', false)->exists()) {
            return redirect()->back()->withDanger('You already have a pending invitation from this clan. Check your invitations.');
        }

        // Create the join request
        ClanInvitation::create([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
            'type' => 'request',
        ]);

        // Notify clan admin
        $clan->admin->systemNotify('clan_request', 'The player ', $user->name, ' has requested to join your clan.', route('clans.index'));

        return redirect()->back()->withSuccess('Your request to join the clan has been sent.');
    }

    public function cancelRequest(ClanInvitation $invitation, Request $request) {
        if ($invitation->user_id !== $request->user()->id || $invitation->type !== 'request') {
            return redirect()->back()->withDanger('You are not allowed to do that.');
        }

        $invitation->delete();

        return redirect()->back()->withSuccess('Your join request has been cancelled.');
    }
}
