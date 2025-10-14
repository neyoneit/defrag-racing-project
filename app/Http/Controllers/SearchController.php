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

        $maps = Map::search($request->search)->paginate(25);

        $users = User::search($request->search)->get();
        $profiles = MddProfile::search($request->search)->get();

        $players = [];

        foreach($profiles as $profile) {
            $exists = false;
            foreach($users as $user) {
                if ($user->mdd_id == NULL) {
                    continue;
                }

                if (intval($user->mdd_id) === $profile->id) {
                    $exists = true;
                }
            }

            if (! $exists) {
                $players[] = [
                    'id'                    => $profile->id,
                    'name'                  => $profile->name,
                    'country'               => $profile->country,
                    'profile_photo_path'    => NULL,
                    'mdd'                   => true
                ];
            }
        }

        $players = array_merge($players, $users->map(function($user) {
            return [
                'id'                    => $user->id,
                'name'                  => $user->name,
                'country'               => $user->country,
                'profile_photo_path'    => $user->profile_photo_path,
                'mdd'                   => false
            ];
        })->toArray());

        // Search models by name or author
        $models = PlayerModel::where('approved', true)
            ->where(function($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('author', 'LIKE', '%' . $request->search . '%');
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
