<?php

namespace App\Http\Controllers\Clans;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Clan;
use App\Models\User;
use App\Models\ClanInvitation;
use App\Models\ClanPlayer;

use App\Http\Controllers\Controller;

use Carbon\Carbon;

class ClansController extends Controller {
    public function index (Request $request) {
        $sortBy = $request->get('sort', 'wrs');
        $sortDir = $request->get('dir', 'desc');

        $query = Clan::with('admin:id,name')
            ->with(['players.user' => function ($q) {
                $q->select('id', 'name', 'profile_photo_path', 'cached_wr_count', 'cached_top3_count');
            }])
            ->withCount('players');

        // Add calculated stats using subqueries
        $query->selectRaw('clans.*,
            (SELECT COALESCE(SUM(users.cached_wr_count), 0)
             FROM clan_players
             JOIN users ON clan_players.user_id = users.id
             WHERE clan_players.clan_id = clans.id) as total_wrs,
            (SELECT COALESCE(SUM(users.cached_top3_count), 0)
             FROM clan_players
             JOIN users ON clan_players.user_id = users.id
             WHERE clan_players.clan_id = clans.id) as total_top3
        ');

        // Sorting
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

        $clans = $query->paginate(20)->appends(['sort' => $sortBy, 'dir' => $sortDir]);

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
                ->has('clan')
                ->with('clan')
                ->get();

            $users = User::query()->orderBy('plain_name')->get(['id', 'name', 'country', 'plain_name']);
        } else {
            $myClan = null;
            $users = [];
            $invitations = [];
        }
        

        return Inertia::render('Clans/Index')
            ->with('clans', $clans)
            ->with('myClan', $myClan)
            ->with('users', $users)
            ->with('invitations', $invitations)
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
                'config_file' => $clanPlayer->config_file
            ];
            return $user;
        });

        // Get clan statistics
        $statisticsController = new \App\Http\Controllers\Clans\ClanStatisticsController();
        $statistics = $statisticsController->getStatistics($clan);

        return Inertia::render('Clans/Show')
            ->with('clan', $clan)
            ->with('players', $players)
            ->with('statistics', $statistics);
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
}
