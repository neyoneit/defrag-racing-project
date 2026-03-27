<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Map;
use App\Models\User;
use App\Models\MddProfile;
use App\Models\PlayerModel;

class SearchController extends Controller
{
    public function search(Request $request) {
        $request->validate([
            'search'    =>      ['required', 'string', 'min:1', 'max:255']
        ]);

        $fuzzyPattern = '%' . implode('%', mb_str_split(strtolower(trim($request->search)))) . '%';

        // Typesense search + DB fuzzy fallback for maps
        $maps = Map::search($request->search)->paginate(25);
        if ($maps->isEmpty()) {
            $maps = Map::where('name', 'LIKE', $fuzzyPattern)
                ->orderBy('date_added', 'DESC')
                ->paginate(25);
        }

        // Typesense search + DB fuzzy fallback for players
        $users = User::search($request->search)->get();
        $profiles = MddProfile::search($request->search)->get();

        if ($users->isEmpty() && $profiles->isEmpty()) {
            $users = User::where('plain_name', 'LIKE', $fuzzyPattern)->limit(25)->get();
            $profiles = MddProfile::where('name', 'LIKE', $fuzzyPattern)->limit(25)->get();
        }

        $players = [];

        // Check which MDD profiles have linked accounts (query DB, not just search results)
        $linkedMddIds = User::whereIn('mdd_id', $profiles->pluck('id'))->pluck('mdd_id', 'id');

        foreach($profiles as $profile) {
            // If this MDD profile has a linked user, show the user instead
            $linkedUserId = $linkedMddIds->search($profile->id);
            if ($linkedUserId !== false) {
                $linkedUser = User::find($linkedUserId);
                if ($linkedUser && !$users->contains('id', $linkedUserId)) {
                    $players[] = [
                        'id'                    => $linkedUser->id,
                        'name'                  => $linkedUser->name,
                        'country'               => $linkedUser->country ?? $profile->country,
                        'profile_photo_path'    => $linkedUser->profile_photo_path,
                        'mdd'                   => false,
                        'mdd_name'              => $profile->name,
                    ];
                }
                continue;
            }

            $players[] = [
                'id'                    => $profile->id,
                'name'                  => $profile->name,
                'country'               => $profile->country,
                'profile_photo_path'    => NULL,
                'mdd'                   => true
            ];
        }

        $players = array_merge($players, $users->map(function($user) use ($request) {
            $mddName = null;
            if ($user->mdd_id) {
                $mddProfile = MddProfile::find($user->mdd_id);
                if ($mddProfile && $mddProfile->name !== $user->name) {
                    $mddName = $mddProfile->name;
                }
            }

            // Find matching alias for search term
            $matchedAlias = null;
            $search = mb_strtolower($request->search);
            $aliases = $user->aliases()->where('is_approved', true)->pluck('alias');
            foreach ($aliases as $alias) {
                $aliasPlain = preg_replace('/\^[\dA-Fa-f]/', '', $alias);
                if (str_contains(mb_strtolower($aliasPlain), $search)) {
                    $matchedAlias = $alias;
                    break;
                }
            }

            return [
                'id'                    => $user->id,
                'name'                  => $user->name,
                'country'               => $user->country,
                'profile_photo_path'    => $user->profile_photo_path,
                'mdd'                   => false,
                'mdd_name'              => $mddName,
                'matched_alias'         => $matchedAlias,
            ];
        })->toArray());

        // Search models by name or author (with fuzzy)
        $fuzzyPattern = '%' . implode('%', mb_str_split(strtolower(trim($request->search)))) . '%';
        $models = PlayerModel::where('approval_status', 'approved')
            ->where(function($query) use ($request, $fuzzyPattern) {
                $query->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('author', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('name', 'LIKE', $fuzzyPattern)
                      ->orWhere('author', 'LIKE', $fuzzyPattern);
            })
            ->limit(10)
            ->get(['id', 'name', 'author', 'head_icon']);

        return [
            'maps'      => $maps,
            'players'   => $players,
            'models'    => $models
        ];
    }
}
