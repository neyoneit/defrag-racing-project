<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Map;
use App\Models\User;
use App\Models\MddProfile;
use App\Models\PlayerModel;
use App\Models\UserAlias;

class SearchController extends Controller
{
    public function search(Request $request) {
        $request->validate([
            'search'    =>      ['required', 'string', 'min:1', 'max:255']
        ]);

        $fuzzyPattern = '%' . implode('%', mb_str_split(strtolower(trim($request->search)))) . '%';
        $rawSearch = trim($request->search);
        $likeSearch = '%' . $rawSearch . '%';

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

        // Alias-based lookup. Names like "uN-DeaD!zatinU" or "Spasibo" only
        // exist in user_aliases (user changed nick mid-career), so a Typesense
        // hit on User.plain_name / MddProfile.name will miss them. Pull any
        // matching alias and fold the owning User / MddProfile into the result
        // set, remembering the literal alias for `matched_alias` display.
        // Approved-only — non-approved aliases are noise (auto-generated from
        // log scrapes, may include impersonations).
        $aliasMatches = UserAlias::where('is_approved', true)
            ->where('alias', 'LIKE', $likeSearch)
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get(['user_id', 'mdd_id', 'alias']);

        // Map of mdd_id -> alias and user_id -> alias so we can decorate
        // existing search hits with their matched_alias even when the primary
        // hit was via plain_name (covers the case where someone has both a
        // matching plain name and a matching alias).
        $aliasByUserId = [];
        $aliasByMddId  = [];
        foreach ($aliasMatches as $a) {
            if ($a->user_id && !isset($aliasByUserId[$a->user_id])) {
                $aliasByUserId[$a->user_id] = $a->alias;
            }
            if ($a->mdd_id && !isset($aliasByMddId[$a->mdd_id])) {
                $aliasByMddId[$a->mdd_id] = $a->alias;
            }
        }

        // Pull users / profiles referenced by aliases that aren't already in
        // the result set, and merge them in.
        $existingUserIds = $users->pluck('id')->all();
        $missingUserIds  = array_diff(array_keys($aliasByUserId), $existingUserIds);
        if (!empty($missingUserIds)) {
            $users = $users->merge(User::whereIn('id', $missingUserIds)->get());
        }

        $existingMddIds = $profiles->pluck('id')->all();
        // Only fetch mdd profiles whose alias has no user_id — when a
        // user_id IS set on the alias, we surface the User instead (matches
        // the existing "linkedUserId → show user, not profile" branch below).
        $orphanMddIds = [];
        foreach ($aliasMatches as $a) {
            if ($a->mdd_id && !$a->user_id && !in_array($a->mdd_id, $existingMddIds, true)) {
                $orphanMddIds[$a->mdd_id] = true;
            }
        }
        if (!empty($orphanMddIds)) {
            $profiles = $profiles->merge(MddProfile::whereIn('id', array_keys($orphanMddIds))->get());
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
                        'matched_alias'         => $aliasByUserId[$linkedUser->id] ?? $aliasByMddId[$profile->id] ?? null,
                    ];
                }
                continue;
            }

            $players[] = [
                'id'                    => $profile->id,
                'name'                  => $profile->name,
                'country'               => $profile->country,
                'profile_photo_path'    => NULL,
                'mdd'                   => true,
                'matched_alias'         => $aliasByMddId[$profile->id] ?? null,
            ];
        }

        $players = array_merge($players, $users->map(function($user) use ($request, $aliasByUserId) {
            $mddName = null;
            if ($user->mdd_id) {
                $mddProfile = MddProfile::find($user->mdd_id);
                if ($mddProfile && $mddProfile->name !== $user->name) {
                    $mddName = $mddProfile->name;
                }
            }

            // Prefer the alias the global lookup already found (already
            // matches the search term). Fall back to scanning the user's
            // approved aliases for a substring match — covers edge cases
            // where the alias lookup didn't hit due to leetspeak / colour
            // differences but the user's `aliases` relation has a stripped
            // version that does match.
            $matchedAlias = $aliasByUserId[$user->id] ?? null;
            if ($matchedAlias === null) {
                $search = mb_strtolower($request->search);
                $aliases = $user->aliases()->where('is_approved', true)->pluck('alias');
                foreach ($aliases as $alias) {
                    $aliasPlain = preg_replace('/\^[\dA-Fa-f]/', '', $alias);
                    if (str_contains(mb_strtolower($aliasPlain), $search)) {
                        $matchedAlias = $alias;
                        break;
                    }
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
