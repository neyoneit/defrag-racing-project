<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\User;
use App\Models\Record;
use App\Models\Map;
use App\Models\MddProfile;
use App\Models\RenderedVideo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProfileController extends Controller {
    public function index(Request $request, $userId) {
        $user = User::query()
            ->where('id', $userId)
            ->with('clan')
            ->first(['id', 'mdd_id', 'name', 'profile_photo_path', 'profile_background_path', 'country', 'color', 'avatar_effect', 'name_effect', 'avatar_border_color', 'discord_id', 'discord_name', 'twitch_id', 'twitch_name', 'twitter_name', 'profile_layout', 'admin', 'is_moderator', 'donation_emails', 'is_live']);

        // Add MDD name if different from site name
        if ($user && $user->mdd_id) {
            $mddProfile = \App\Models\MddProfile::find($user->mdd_id);
            $user->mdd_name = $mddProfile?->name;
        }

        if (! $user) {
            return redirect()->route('profile.mdd', $userId);
        }

        if (! $user->mdd_id ) {
            return Inertia::render('Profile')
                ->with('user', $user)
                ->with('vq3Records', (object)['total' => 0, 'data' => [], 'per_page' => 20])
                ->with('cpmRecords', (object)['total' => 0, 'data' => [], 'per_page' => 20])
                ->with('hasProfile', false)
                ->with('hasMapperProfile', $user->hasMapperProfile())
                ->with('hasModelerProfile', $user->hasModelerProfile())
                ->with('creatorClaimNames', $user->mapperClaims()->select('name', 'type')->get())
                ->with('isDonor', $user->isDonor())
                ->with('donorTier', $user->getDonorTier())
                ->with('donationTotal', $user->isDonor() ? $user->getDonationTotal() : [])
                ->with('tagCount', $user->getTagCount());
        }

        // Check which props Inertia is requesting (partial reload)
        $only = $request->header('X-Inertia-Partial-Data');
        $partialProps = $only ? explode(',', $only) : [];
        $isPartial = !empty($partialProps);

        // Helper: should we compute this prop?
        $needs = function ($prop) use ($isPartial, $partialProps) {
            return !$isPartial || in_array($prop, $partialProps);
        };

        $mddId = $user->mdd_id;
        $type = $request->input('type', 'latest');
        $mode = $request->input('mode', 'all');

        // --- Records (needed for records pagination or full load) ---
        if ($needs('vq3Records') || $needs('cpmRecords')) {
            $types = ['recentlybeaten', 'tiedranks', 'bestranks', 'besttimes', 'worstranks', 'worsttimes', 'untouchable'];
            if (!in_array($type, $types)) {
                $type = 'latest';
            }

            $baseRecords = match ($type) {
                'recentlybeaten'    => $this->recentlyBeaten($mddId),
                'tiedranks'         => $this->tiedRanks($mddId),
                'bestranks'         => $this->bestRanks($mddId),
                'besttimes'         => $this->bestTimes($mddId),
                'worstranks'        => $this->worstRanks($mddId),
                'worsttimes'        => $this->worstTimes($mddId),
                'untouchable'       => $this->untouchableWRs($mddId),
                default             => $this->latestRecords($mddId),
            };

            $tablePrefix = ($type === 'recentlybeaten' || $type === 'tiedranks' || $type === 'besttimes' || $type === 'worsttimes') ? 'a' : 'records';

            if ($mode == 'run') {
                $baseRecords->where($tablePrefix . '.mode', 'run');
            } elseif ($mode == 'ctf') {
                $baseRecords->where($tablePrefix . '.mode', 'LIKE', 'ctf%');
            } elseif (in_array($mode, ['ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'])) {
                $baseRecords->where($tablePrefix . '.mode', $mode);
            }

            $vq3Records = clone $baseRecords;
            $vq3Records = $vq3Records->where($tablePrefix . '.physics', 'vq3');
            if ($tablePrefix === 'records') {
                $vq3Records = $vq3Records->with(['map' => fn($q) => $q->select('name', 'thumbnail')]);
            }
            $vq3Records = $vq3Records->paginate(20, ['*'], 'vq3_page')->withQueryString();

            $cpmRecords = clone $baseRecords;
            $cpmRecords = $cpmRecords->where($tablePrefix . '.physics', 'cpm');
            if ($tablePrefix === 'records') {
                $cpmRecords = $cpmRecords->with(['map' => fn($q) => $q->select('name', 'thumbnail')]);
            }
            $cpmRecords = $cpmRecords->paginate(20, ['*'], 'cpm_page')->withQueryString();
        }

        // --- Profile stats (cached + consolidated: ~25 queries → 6) ---
        if (!$isPartial) {
            $stats = $this->getProfileStats($mddId);

            $profileData = $user->mdd_profile;
            if ($profileData) {
                foreach ($stats as $key => $value) {
                    $profileData->$key = $value;
                }
            }

            // Activity heatmap
            $activityYear = (int) $request->input('activity_year', date('Y'));
            $activityData = $this->getActivityData($mddId, $activityYear);
            $activityYears = $this->getActivityYears($mddId);

            // Maplists
            $isOwnProfile = auth()->check() && auth()->id() === $user->id;
            if ($isOwnProfile) {
                $playLater = \App\Models\Maplist::where('user_id', $user->id)->where('is_play_later', true)->first();
                $publicMaplists = \App\Models\Maplist::where('user_id', $user->id)->where('is_public', true)->orderBy('favorites_count', 'desc')->limit(3)->get();
                $userMaplists = collect();
                if ($playLater) $userMaplists->push($playLater);
                $userMaplists = $userMaplists->merge($publicMaplists);
            } else {
                $userMaplists = \App\Models\Maplist::where('user_id', $user->id)->where('is_public', true)->orderBy('favorites_count', 'desc')->limit(3)->get();
            }

            // Aliases
            $isOwnProfile = $isOwnProfile ?? (auth()->check() && auth()->id() === $user->id);
            $canManageAliases = false;
            $aliasSuggestions = null;
            $canSuggestAlias = false;

            if ($isOwnProfile) {
                $aliases = \App\Models\UserAlias::where('user_id', $user->id)->orderBy('created_at', 'desc')->get(['id', 'alias', 'is_approved', 'created_at']);
                $canManageAliases = true;
                $aliasSuggestions = \App\Models\AliasSuggestion::where('user_id', $user->id)->where('status', 'pending')->with('suggestedBy:id,name,profile_photo_path')->orderBy('created_at', 'desc')->get();
            } else {
                $aliases = \App\Models\UserAlias::where('user_id', $user->id)->where('is_approved', true)->orderBy('created_at', 'desc')->get(['alias']);
                if (auth()->check()) {
                    $canSuggestAlias = auth()->user()->canReportDemos();
                }
            }

            // Demos (cached - consolidated 5+1 queries into 1+1, cached 1 hour)
            $topDownloadedDemos = Cache::remember("profile:top_demos:{$user->id}", 3600, function () use ($user) {
                return \App\Models\UploadedDemo::where('user_id', $user->id)->where('download_count', '>', 0)->orderBy('download_count', 'desc')->limit(5)->get(['id', 'original_filename', 'processed_filename', 'map_name', 'time_ms', 'download_count', 'record_id']);
            });
            $demoStats = Cache::remember("profile:demo_stats:{$user->id}", 3600, function () use ($user, $topDownloadedDemos) {
                $stats = DB::table('uploaded_demos')
                    ->where('user_id', $user->id)
                    ->selectRaw('COUNT(*) as total_demos, COALESCE(SUM(download_count), 0) as total_downloads, SUM(CASE WHEN download_count > 0 THEN 1 ELSE 0 END) as demos_with_downloads, COUNT(DISTINCT map_name) as unique_maps')
                    ->first();
                return [
                    'total_demos' => (int) ($stats->total_demos ?? 0),
                    'total_downloads' => (int) ($stats->total_downloads ?? 0),
                    'demos_with_downloads' => (int) ($stats->demos_with_downloads ?? 0),
                    'unique_maps' => (int) ($stats->unique_maps ?? 0),
                    'most_downloaded' => $topDownloadedDemos->first()?->download_count ?? 0,
                ];
            });
        }

        // --- Unplayed maps (partial or full) ---
        if ($needs('unplayed_maps')) {
            $unplayedMaps = $this->getUnplayedMaps($mddId, $request->input('unplayed_page', 1));
            $totalMaps = DB::table('maps')->count();
        }

        // Build response - only include what's needed
        $response = Inertia::render('Profile')
            ->with('user', $user)
            ->with('type', $type)
            ->with('hasProfile', true)
            ->with('hasMapperProfile', $user->hasMapperProfile())
            ->with('hasModelerProfile', $user->hasModelerProfile())
            ->with('creatorClaimNames', $user->mapperClaims()->select('name', 'type')->get())
            ->with('isDonor', $user->isDonor())
            ->with('donorTier', $user->getDonorTier())
            ->with('donationTotal', $user->isDonor() ? $user->getDonationTotal() : [])
            ->with('tagCount', $user->getTagCount());

        if ($needs('vq3Records')) $response->with('vq3Records', $vq3Records ?? (object)['total' => 0, 'data' => [], 'per_page' => 20]);
        if ($needs('cpmRecords')) $response->with('cpmRecords', $cpmRecords ?? (object)['total' => 0, 'data' => [], 'per_page' => 20]);
        if ($needs('unplayed_maps')) {
            $response->with('unplayed_maps', $unplayedMaps ?? collect());
            $response->with('total_maps', $totalMaps ?? 0);
        }

        if (!$isPartial) {
            $response->with('cpm_world_records', $stats['cpm_world_records'])
                ->with('vq3_world_records', $stats['vq3_world_records'])
                ->with('profile', $profileData)
                ->with('user_maplists', $userMaplists)
                ->with('aliases', $aliases)
                ->with('can_manage_aliases', $canManageAliases)
                ->with('alias_suggestions', $aliasSuggestions)
                ->with('can_suggest_alias', $canSuggestAlias)
                ->with('topDownloadedDemos', $topDownloadedDemos)
                ->with('demoStats', $demoStats)
                ->with('renderStats', $this->getRenderStats($user->id))
                ->with('latestRenderedVideos', $this->getLatestRenderedVideos($user->id))
                ->with('activity_data', $activityData)
                ->with('activity_year', $activityYear)
                ->with('activity_years', $activityYears)
                ->with('load_times', []);
        }

        return $response;
    }

    public function mdd(Request $request, $userId) {
        $user = MddProfile::where('id', $userId)->with('user')->first();

        if (! $user) {
            return redirect()->route('home');
        }

        // Profile stats (cached + consolidated)
        $stats = $this->getProfileStats($user->id);

        $type = $request->input('type', 'latest');
        $mode = $request->input('mode', 'all');

        $types = ['recentlybeaten', 'tiedranks', 'bestranks', 'besttimes', 'worstranks', 'worsttimes', 'untouchable'];

        if (!in_array($type, $types)) {
            $type = 'latest';
        }

        $baseRecords = match ($type) {
            'recentlybeaten'    => $this->recentlyBeaten($user->id),
            'tiedranks'         => $this->tiedRanks($user->id),
            'bestranks'         => $this->bestRanks($user->id),
            'besttimes'         => $this->bestTimes($user->id),
            'worstranks'        => $this->worstRanks($user->id),
            'worsttimes'        => $this->worstTimes($user->id),
            'untouchable'       => $this->untouchableWRs($user->id),
            default             => $this->latestRecords($user->id),
        };

        // Apply mode filter (use table alias 'a' for queries that use it, otherwise default 'records')
        $tablePrefix = ($type === 'recentlybeaten' || $type === 'tiedranks' || $type === 'besttimes' || $type === 'worsttimes') ? 'a' : 'records';

        if ($mode == 'run') {
            $baseRecords->where($tablePrefix . '.mode', 'run');
        } elseif ($mode == 'ctf') {
            $baseRecords->where($tablePrefix . '.mode', 'LIKE', 'ctf%');
        } elseif (in_array($mode, ['ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'])) {
            $baseRecords->where($tablePrefix . '.mode', $mode);
        }

        // Get VQ3 records (20 per page)
        $vq3Records = clone $baseRecords;
        $vq3Records = $vq3Records->where($tablePrefix . '.physics', 'vq3');

        // Only eager load 'map' if not using table aliases (to avoid soft delete conflicts)
        if ($tablePrefix === 'records') {
            $vq3Records = $vq3Records->with(['map' => fn($q) => $q->select('name', 'thumbnail')]);
        }
        $vq3Records = $vq3Records->paginate(20, ['*'], 'vq3_page')->withQueryString();

        // Get CPM records (20 per page)
        $cpmRecords = clone $baseRecords;
        $cpmRecords = $cpmRecords->where($tablePrefix . '.physics', 'cpm');

        // Only eager load 'map' if not using table aliases (to avoid soft delete conflicts)
        if ($tablePrefix === 'records') {
            $cpmRecords = $cpmRecords->with(['map' => fn($q) => $q->select('name', 'thumbnail')]);
        }
        $cpmRecords = $cpmRecords->paginate(20, ['*'], 'cpm_page')->withQueryString();

        // Add cached stats to profile data
        foreach ($stats as $key => $value) {
            $user->$key = $value;
        }

        // Get activity heatmap data
        $activityYear = (int) $request->input('activity_year', date('Y'));
        $activityData = $this->getActivityData($user->id, $activityYear);
        $activityYears = $this->getActivityYears($user->id);

        // Get unplayed maps for completionist list
        $unplayedMaps = $this->getUnplayedMaps($user->id, $request->input('unplayed_page', 1));
        $totalMaps = DB::table('maps')->count();

        // For MDD profiles without a linked web account, create a minimal user object
        $linkedUser = $user->user ?? (object) [
            'id' => null,
            'name' => $user->name,
            'country' => $user->country,
            'profile_photo_path' => null,
            'profile_background_path' => null,
            'color' => '#ffffff',
            'avatar_effect' => 'none',
            'name_effect' => 'none',
            'avatar_border_color' => '#6b7280',
            'discord_name' => null,
            'twitch_name' => null,
            'twitter_name' => null,
            'is_live' => false,
            'clan' => null,
        ];

        return Inertia::render('Profile')
            ->with('vq3Records', $vq3Records)
            ->with('cpmRecords', $cpmRecords)
            ->with('user', $linkedUser)
            ->with('type', $type)
            ->with('cpm_world_records', $stats['cpm_world_records'])
            ->with('vq3_world_records', $stats['vq3_world_records'])
            ->with('profile', $user)
            ->with('unplayed_maps', $unplayedMaps)
            ->with('total_maps', $totalMaps)
            ->with('hasProfile', true)
            ->with('aliases', [])
            ->with('alias_suggestions', [])
            ->with('can_suggest_alias', false)
            ->with('can_manage_aliases', false)
            ->with('user_maplists', [])
            ->with('topDownloadedDemos', [])
            ->with('activity_data', $activityData)
            ->with('activity_year', $activityYear)
            ->with('activity_years', $activityYears)
            ->with('hasMapperProfile', false)
            ->with('hasModelerProfile', false)
            ->with('creatorClaimNames', [])
            ->with('isDonor', false)
            ->with('donorTier', null)
            ->with('donationTotal', [])
            ->with('tagCount', 0);
    }

    public function latestRecords($mddId) {
        $records = Record::where('mdd_id', $mddId)->orderBy('date_set', 'DESC');

        return $records;
    }

    public function recentlyBeaten($mddId) {
        // Get records that beat your BEST time on each map
        // First, get a subquery of user's best times per map
        $myBestTimes = DB::table('records')
            ->select('mapname', 'gametype', DB::raw('MIN(time) as best_time'))
            ->where('mdd_id', $mddId)
            ->whereNull('deleted_at')
            ->groupBy('mapname', 'gametype');

        $records = Record::select(
                'a.*',
                'my_times.best_time as my_time',
                'a.rank as rank_num',
                'mdd_profiles.plain_name as mdd_plain_name',
                'users.name as user_name',
                'users.plain_name as user_plain_name',
                'users.profile_photo_path as user_profile_photo_path',
                'users.country as user_country',
                'users.color as user_color',
                'users.avatar_effect as user_avatar_effect',
                'users.name_effect as user_name_effect',
                'users.avatar_border_color as user_avatar_border_color'
            )
            ->from('records as a')
            ->joinSub($myBestTimes, 'my_times', function($join) {
                $join->on('a.mapname', '=', 'my_times.mapname')
                     ->on('a.gametype', '=', 'my_times.gametype')
                     ->where('a.time', '<', DB::raw('my_times.best_time'));
            })
            ->leftJoin('mdd_profiles', 'a.mdd_id', '=', 'mdd_profiles.id')
            ->leftJoin('users', 'mdd_profiles.user_id', '=', 'users.id')
            ->where('a.mdd_id', '!=', $mddId)
            ->whereNull('a.deleted_at')
            ->withTrashed()
            ->orderBy('a.date_set', 'DESC');

        return $records;
    }

    public function tiedRanks($mddId) {
        // Get your current records (one per map/gametype)
        $myRecords = DB::table('records')
            ->select('mapname', 'gametype', 'rank')
            ->where('mdd_id', $mddId)
            ->whereNull('deleted_at')
            ->groupBy('mapname', 'gametype', 'rank');

        $records = Record::select(
                'a.*',
                'my_records.rank as my_rank',
                'mdd_profiles.plain_name as mdd_plain_name',
                'users.name as user_name',
                'users.plain_name as user_plain_name',
                'users.profile_photo_path as user_profile_photo_path',
                'users.country as user_country',
                'users.color as user_color',
                'users.avatar_effect as user_avatar_effect',
                'users.name_effect as user_name_effect',
                'users.avatar_border_color as user_avatar_border_color'
            )
            ->from('records as a')
            ->joinSub($myRecords, 'my_records', function($join) {
                $join->on('a.mapname', '=', 'my_records.mapname')
                     ->on('a.gametype', '=', 'my_records.gametype')
                     ->on('a.rank', '=', 'my_records.rank');
            })
            ->leftJoin('mdd_profiles', 'a.mdd_id', '=', 'mdd_profiles.id')
            ->leftJoin('users', 'mdd_profiles.user_id', '=', 'users.id')
            ->where('a.mdd_id', '!=', $mddId)
            ->whereNull('a.deleted_at')
            ->withTrashed()
            ->orderBy('a.date_set', 'DESC');

        return $records;
    }

    public function bestRanks($mddId) {
        $records = Record::where('mdd_id', $mddId)->orderBy('rank', 'ASC')->orderBy('date_set', 'DESC');

        return $records;
    }

    public function bestTimes($mddId) {
        $records =  Record::selectRaw("
            a.*,
                (SELECT count(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype AND time<a.time ORDER by time) as rank_num,
                (SELECT count(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype) as rank_total,
                (SELECT time FROM records WHERE mapname=a.mapname AND gametype=a.gametype ORDER BY TIME LIMIT 1) AS time_first,
                (SELECT 1-((rank_num+1)/(rank_total+1))) AS skill
        ")
        ->from('records as a')
        ->whereRaw('a.mdd_id = ?', [$mddId])
        ->whereRaw('a.deleted_at IS NULL')
        ->withTrashed()
        ->orderBy('skill', 'DESC')
        ->orderByRaw('a.date_set DESC');

        return $records;
    }

    public function worstRanks($mddId) {
        $records = Record::where('mdd_id', $mddId)->orderBy('rank', 'DESC')->orderBy('date_set', 'DESC');

        return $records;
    }

    public function worstTimes($mddId) {
        $records =  Record::selectRaw("
            a.*,
                (SELECT count(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype AND time<a.time ORDER by time) as rank_num,
                (SELECT count(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype) as rank_total,
                (SELECT time FROM records WHERE mapname=a.mapname AND gametype=a.gametype ORDER BY TIME LIMIT 1) AS time_first,
                (SELECT 1-((rank_num+1)/(rank_total+1))) AS skill
        ")
        ->from('records as a')
        ->whereRaw('a.mdd_id = ?', [$mddId])
        ->whereRaw('a.deleted_at IS NULL')
        ->withTrashed()
        ->orderBy('skill', 'ASC')
        ->orderByRaw('a.date_set DESC');

        return $records;
    }

    public function untouchableWRs($mddId) {
        // Get world records (rank = 1) that have been held for over 1 year
        $oneYearAgo = now()->subYear();

        $records = Record::where('mdd_id', $mddId)
            ->where('rank', 1)
            ->where('date_set', '<=', $oneYearAgo)
            ->orderBy('date_set', 'ASC');

        return $records;
    }

    private function calculateSkillScore($mddId, $physics) {
        // Calculate a skill score based on multiple factors
        $wrs = Record::where('mdd_id', $mddId)->where('physics', $physics)->where('rank', 1)->count();
        $top3 = Record::where('mdd_id', $mddId)->where('physics', $physics)->whereBetween('rank', [1, 3])->count();
        $top10 = Record::where('mdd_id', $mddId)->where('physics', $physics)->whereBetween('rank', [1, 10])->count();
        $avgRank = Record::where('mdd_id', $mddId)->where('physics', $physics)->avg('rank') ?? 999;
        $totalRecords = Record::where('mdd_id', $mddId)->where('physics', $physics)->count();

        // Weighted skill score: WRs count most, then top3, top10, inverse of avg rank, and total activity
        $score = ($wrs * 100) + ($top3 * 50) + ($top10 * 20) + (1000 / max($avgRank, 1)) + ($totalRecords * 2);

        return $score;
    }

    public function getCompetitors($mddId, $physics = 'cpm') {
        $startTime = microtime(true);

        // Get current user's skill score
        $myScore = $this->calculateSkillScore($mddId, $physics);

        \Log::info('GetCompetitors called', ['mdd_id' => $mddId, 'physics' => $physics, 'my_score' => $myScore]);

        // Get all players with their skill scores (optimized query)
        $allPlayers = DB::select("
            SELECT
                mdd_id,
                COUNT(*) as total_records,
                SUM(CASE WHEN `rank` = 1 THEN 1 ELSE 0 END) as wrs,
                SUM(CASE WHEN `rank` BETWEEN 1 AND 3 THEN 1 ELSE 0 END) as top3,
                SUM(CASE WHEN `rank` BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as top10,
                AVG(`rank`) as avg_rank
            FROM records
            WHERE physics = ? AND mdd_id IS NOT NULL AND mdd_id != ? AND deleted_at IS NULL
            GROUP BY mdd_id
            HAVING total_records >= 3
            LIMIT 200
        ", [$physics, $mddId]);

        \Log::info('All players count', ['count' => count($allPlayers)]);

        // Calculate skill scores and find closest competitors
        $players = [];
        foreach ($allPlayers as $player) {
            $score = ($player->wrs * 100) + ($player->top3 * 50) + ($player->top10 * 20) +
                     (1000 / max($player->avg_rank, 1)) + ($player->total_records * 2);

            $players[] = [
                'mdd_id' => $player->mdd_id,
                'score' => $score,
                'diff' => abs($score - $myScore),
                'better' => $score > $myScore,
                'wrs' => $player->wrs,
                'total_records' => $player->total_records
            ];
        }

        // Sort by score difference to find closest competitors
        usort($players, function($a, $b) {
            return $a['diff'] <=> $b['diff'];
        });

        // Get 2 better and 2 worse players
        $better = array_values(array_filter($players, fn($p) => $p['better']));
        $worse = array_values(array_filter($players, fn($p) => !$p['better']));

        $better = array_slice($better, 0, 2);
        $worse = array_slice($worse, 0, 2);

        // Load player details
        $betterIds = array_column($better, 'mdd_id');
        $worseIds = array_column($worse, 'mdd_id');

        $betterPlayers = MddProfile::whereIn('id', $betterIds)->with('user')->get()->keyBy('id');
        $worsePlayers = MddProfile::whereIn('id', $worseIds)->with('user')->get()->keyBy('id');

        // Merge score data with player details
        $betterWithDetails = collect($better)->map(function($p) use ($betterPlayers) {
            $player = $betterPlayers->get($p['mdd_id']);
            if (!$player) return null;
            return [
                'id' => $player->id,
                'name' => $player->name,
                'plain_name' => $player->plain_name,
                'country' => $player->country,
                'user' => $player->user,
                'score' => $p['score'],
                'wrs' => $p['wrs'],
                'total_records' => $p['total_records']
            ];
        })->filter()->values()->toArray();

        $worseWithDetails = collect($worse)->map(function($p) use ($worsePlayers) {
            $player = $worsePlayers->get($p['mdd_id']);
            if (!$player) return null;
            return [
                'id' => $player->id,
                'name' => $player->name,
                'plain_name' => $player->plain_name,
                'country' => $player->country,
                'user' => $player->user,
                'score' => $p['score'],
                'wrs' => $p['wrs'],
                'total_records' => $p['total_records']
            ];
        })->filter()->values()->toArray();

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds

        \Log::info('GetCompetitors completed', ['duration_ms' => $duration]);

        return [
            'better' => $betterWithDetails,
            'worse' => $worseWithDetails,
            'my_score' => $myScore
        ];
    }

    public function getRivals($mddId, $physics = 'cpm') {
        $startTime = microtime(true);

        // Find players you've beaten most (you have better time on same map)
        $beaten = DB::select("
            SELECT
                r2.mdd_id,
                COUNT(DISTINCT r2.mapname) as maps_beaten,
                COUNT(*) as times_beaten
            FROM records r1
            JOIN records r2 ON r1.mapname = r2.mapname
                AND r1.gametype = r2.gametype
                AND r1.physics = r2.physics
                AND r1.time < r2.time
            WHERE r1.mdd_id = ?
                AND r2.mdd_id != ?
                AND r1.physics = ?
                AND r1.deleted_at IS NULL
                AND r2.deleted_at IS NULL
            GROUP BY r2.mdd_id
            ORDER BY times_beaten DESC
            LIMIT 2
        ", [$mddId, $mddId, $physics]);

        // Find players who've beaten you most
        $beatenBy = DB::select("
            SELECT
                r1.mdd_id,
                COUNT(DISTINCT r1.mapname) as maps_beaten,
                COUNT(*) as times_beaten
            FROM records r1
            JOIN records r2 ON r1.mapname = r2.mapname
                AND r1.gametype = r2.gametype
                AND r1.physics = r2.physics
                AND r1.time < r2.time
            WHERE r2.mdd_id = ?
                AND r1.mdd_id != ?
                AND r1.physics = ?
                AND r1.deleted_at IS NULL
                AND r2.deleted_at IS NULL
            GROUP BY r1.mdd_id
            ORDER BY times_beaten DESC
            LIMIT 2
        ", [$mddId, $mddId, $physics]);

        // Load player profiles
        $beatenIds = array_column($beaten, 'mdd_id');
        $beatenByIds = array_column($beatenBy, 'mdd_id');

        $beatenPlayers = MddProfile::whereIn('id', $beatenIds)->with('user')->get()->keyBy('id');
        $beatenByPlayers = MddProfile::whereIn('id', $beatenByIds)->with('user')->get()->keyBy('id');

        $beatenWithDetails = collect($beaten)->map(function($r) use ($beatenPlayers) {
            $player = $beatenPlayers->get($r->mdd_id);
            if (!$player) return null;
            return [
                'id' => $player->id,
                'name' => $player->name,
                'plain_name' => $player->plain_name,
                'country' => $player->country,
                'user' => $player->user,
                'maps_beaten' => $r->maps_beaten,
                'times_beaten' => $r->times_beaten
            ];
        })->filter()->values()->toArray();

        $beatenByWithDetails = collect($beatenBy)->map(function($r) use ($beatenByPlayers) {
            $player = $beatenByPlayers->get($r->mdd_id);
            if (!$player) return null;
            return [
                'id' => $player->id,
                'name' => $player->name,
                'plain_name' => $player->plain_name,
                'country' => $player->country,
                'user' => $player->user,
                'maps_beaten' => $r->maps_beaten,
                'times_beaten' => $r->times_beaten
            ];
        })->filter()->values()->toArray();

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds

        \Log::info('GetRivals completed', ['duration_ms' => $duration]);

        return [
            'beaten' => $beatenWithDetails,
            'beaten_by' => $beatenByWithDetails
        ];
    }

    public function getBeatableRecords($myMddId, $rivalMddId, $physics = 'cpm') {
        // Find records where:
        // 1. Rival has a record
        // 2. You either don't have a record OR your record is worse but within 20% time difference (beatable)

        $beatableRecords = DB::select("
            SELECT
                rival.mapname,
                rival.mode,
                rival.physics,
                rival.time as rival_time,
                rival.rank as rival_rank,
                me.time as my_time,
                me.rank as my_rank,
                rival.date_set as rival_date,
                CASE
                    WHEN me.time IS NULL THEN 0
                    ELSE ABS(rival.time - me.time) / rival.time * 100
                END as time_diff_percent,
                CASE
                    WHEN me.time IS NULL THEN 'no_record'
                    WHEN me.time > rival.time THEN 'behind'
                    ELSE 'ahead'
                END as status
            FROM records rival
            LEFT JOIN records me ON rival.mapname = me.mapname
                AND rival.mode = me.mode
                AND rival.physics = me.physics
                AND me.mdd_id = ?
            WHERE rival.mdd_id = ?
                AND rival.physics = ?
                AND rival.deleted_at IS NULL
                AND (me.deleted_at IS NULL OR me.deleted_at IS NOT NULL)
                AND (
                    me.time IS NULL
                    OR (me.time > rival.time AND (me.time - rival.time) / rival.time <= 0.3)
                )
            ORDER BY
                CASE
                    WHEN me.time IS NULL THEN 2
                    ELSE 1
                END,
                time_diff_percent ASC
            LIMIT 50
        ", [$myMddId, $rivalMddId, $physics]);

        return $beatableRecords;
    }

    public function beatableRecordsApi($userId, $rivalMddId, Request $request) {
        $user = User::find($userId);
        if (!$user || !$user->mdd_id) {
            return response()->json([]);
        }

        $physics = $request->input("physics", "cpm");
        $records = $this->getBeatableRecords($user->mdd_id, $rivalMddId, $physics);

        return response()->json($records);
    }

    public function searchPlayers(Request $request) {
        $query = $request->input('q');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $players = MddProfile::with('user')
            ->where(function($q) use ($query) {
                $q->where('plain_name', 'like', '%' . $query . '%')
                  ->orWhere('name', 'like', '%' . $query . '%');
            })
            ->limit(10)
            ->get();

        return response()->json($players);
    }

    public function comparePlayer($userId, $rivalId, Request $request) {
        $user = User::find($userId);
        $rival = User::find($rivalId);

        if (!$user || !$user->mdd_id || !$rival || !$rival->mdd_id) {
            return response()->json([]);
        }

        $physics = $request->input('physics', 'cpm');

        // Get head-to-head comparison
        $comparison = DB::select("
            SELECT
                rival.mapname,
                rival.mode,
                rival.physics,
                rival.time as rival_time,
                rival.rank as rival_rank,
                me.time as my_time,
                me.rank as my_rank,
                CASE
                    WHEN me.time IS NULL THEN NULL
                    ELSE ABS(rival.time - me.time)
                END as time_diff,
                CASE
                    WHEN me.time IS NULL THEN 'no_record'
                    WHEN me.time > rival.time THEN 'behind'
                    ELSE 'ahead'
                END as status
            FROM records rival
            LEFT JOIN records me ON rival.mapname = me.mapname
                AND rival.mode = me.mode
                AND rival.physics = me.physics
                AND me.mdd_id = ?
                AND me.deleted_at IS NULL
            WHERE rival.mdd_id = ?
                AND rival.physics = ?
                AND rival.deleted_at IS NULL
            ORDER BY
                CASE
                    WHEN me.time IS NULL THEN 2
                    WHEN me.time > rival.time THEN 1
                    ELSE 3
                END,
                time_diff ASC
            LIMIT 50
        ", [$user->mdd_id, $rival->mdd_id, $physics]);

        return response()->json($comparison);
    }

    /**
     * Get competitors with 1-day cache
     */
    protected function getCachedCompetitors($mddId, $physics) {
        $cacheKey = "profile:competitors:{$mddId}:{$physics}";

        if (Cache::has($cacheKey)) {
            \Log::info("Cache HIT for {$cacheKey}");
        } else {
            \Log::info("Cache MISS for {$cacheKey} - calculating...");
        }

        return Cache::remember($cacheKey, 86400, fn() => $this->getCompetitors($mddId, $physics));
    }

    /**
     * Get rivals with 1-day cache
     */
    protected function getCachedRivals($mddId, $physics) {
        $cacheKey = "profile:rivals:{$mddId}:{$physics}";
        return Cache::remember($cacheKey, 86400, fn() => $this->getRivals($mddId, $physics));
    }

    /**
     * Clear profile cache for a specific player
     */
    public static function clearPlayerCache($mddId) {
        Cache::forget("profile:stats:{$mddId}");
        Cache::forget("profile:competitors:{$mddId}:cpm");
        Cache::forget("profile:competitors:{$mddId}:vq3");
        Cache::forget("profile:rivals:{$mddId}:cpm");
        Cache::forget("profile:rivals:{$mddId}:vq3");
    }

    /**
     * Get consolidated profile stats (cached for 1 hour)
     * Replaces ~25 individual queries with 6 consolidated ones
     */
    protected function getProfileStats($mddId)
    {
        return Cache::remember("profile:stats:{$mddId}", 3600, function () use ($mddId) {
            // Query 1: Basic stats grouped by physics (replaces 12 individual COUNT queries)
            $basicStats = DB::table('records')
                ->select([
                    'physics',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN `rank` = 1 THEN 1 ELSE 0 END) as wrs'),
                    DB::raw('SUM(CASE WHEN `rank` BETWEEN 1 AND 3 THEN 1 ELSE 0 END) as top3'),
                    DB::raw('SUM(CASE WHEN `rank` BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as top10'),
                    DB::raw('COUNT(DISTINCT mapname) as unique_maps'),
                    DB::raw('AVG(`rank`) as avg_rank'),
                ])
                ->where('mdd_id', $mddId)
                ->whereNull('deleted_at')
                ->groupBy('physics')
                ->get()
                ->keyBy('physics');

            $cpm = $basicStats->get('cpm');
            $vq3 = $basicStats->get('vq3');

            // Query 2: Map feature stats (replaces 6 individual JOIN+COUNT queries)
            $featureStats = DB::table('records')
                ->join('maps', 'records.mapname', '=', 'maps.name')
                ->select([
                    'records.physics',
                    DB::raw("SUM(CASE WHEN maps.functions LIKE '%slick%' THEN 1 ELSE 0 END) as slick"),
                    DB::raw("SUM(CASE WHEN maps.functions LIKE '%jumppad%' THEN 1 ELSE 0 END) as jumppad"),
                    DB::raw("SUM(CASE WHEN maps.functions LIKE '%tele%' THEN 1 ELSE 0 END) as teleporter"),
                ])
                ->where('records.mdd_id', $mddId)
                ->whereNull('records.deleted_at')
                ->groupBy('records.physics')
                ->get()
                ->keyBy('physics');

            $featureCpm = $featureStats->get('cpm');
            $featureVq3 = $featureStats->get('vq3');

            // Query 3: Longest streak
            $longestStreak = DB::select("SELECT MAX(streak) as longest_streak FROM (SELECT @streak := IF(@prev_date = DATE(date_set) - INTERVAL 1 DAY, @streak + 1, 1) as streak, @prev_date := DATE(date_set) as date_set FROM records, (SELECT @streak := 0, @prev_date := NULL) vars WHERE mdd_id = ? AND deleted_at IS NULL ORDER BY date_set) streaks", [$mddId]);

            // Query 4: First record date
            $firstRecordDate = DB::table('records')->where('mdd_id', $mddId)->whereNull('deleted_at')->orderBy('date_set', 'ASC')->value('date_set');

            // Query 5: Most active month
            $mostActiveMonth = DB::select("SELECT DATE_FORMAT(date_set, '%Y-%m') as month, COUNT(*) as count FROM records WHERE mdd_id = ? AND deleted_at IS NULL GROUP BY month ORDER BY count DESC LIMIT 1", [$mddId]);

            // Query 6: Marathon record (longest time)
            $marathonRecord = DB::table('records')->where('mdd_id', $mddId)->whereNull('deleted_at')->orderBy('time', 'DESC')->first(['time', 'mapname', 'physics', 'mode']);

            $worldRecordsCpm = (int) ($cpm->wrs ?? 0);
            $worldRecordsVq3 = (int) ($vq3->wrs ?? 0);
            $uniqueMapsCpm = (int) ($cpm->unique_maps ?? 0);
            $uniqueMapsVq3 = (int) ($vq3->unique_maps ?? 0);

            return [
                'cpm_records' => (int) ($cpm->total ?? 0),
                'vq3_records' => (int) ($vq3->total ?? 0),
                'cpm_world_records' => $worldRecordsCpm,
                'vq3_world_records' => $worldRecordsVq3,
                'cpm_top3' => (int) ($cpm->top3 ?? 0),
                'vq3_top3' => (int) ($vq3->top3 ?? 0),
                'cpm_top10' => (int) ($cpm->top10 ?? 0),
                'vq3_top10' => (int) ($vq3->top10 ?? 0),
                'cpm_unique_maps' => $uniqueMapsCpm,
                'vq3_unique_maps' => $uniqueMapsVq3,
                'cpm_avg_rank' => $cpm ? round($cpm->avg_rank, 1) : 0,
                'vq3_avg_rank' => $vq3 ? round($vq3->avg_rank, 1) : 0,
                'cpm_slick' => (int) ($featureCpm->slick ?? 0),
                'vq3_slick' => (int) ($featureVq3->slick ?? 0),
                'cpm_jumppad' => (int) ($featureCpm->jumppad ?? 0),
                'vq3_jumppad' => (int) ($featureVq3->jumppad ?? 0),
                'cpm_teleporter' => (int) ($featureCpm->teleporter ?? 0),
                'vq3_teleporter' => (int) ($featureVq3->teleporter ?? 0),
                'longest_streak' => $longestStreak[0]->longest_streak ?? 0,
                'cpm_dominance' => $uniqueMapsCpm > 0 ? round(($worldRecordsCpm / $uniqueMapsCpm) * 100, 1) : 0,
                'vq3_dominance' => $uniqueMapsVq3 > 0 ? round(($worldRecordsVq3 / $uniqueMapsVq3) * 100, 1) : 0,
                'first_record_date' => $firstRecordDate,
                'most_active_month' => $mostActiveMonth[0] ?? null,
                'marathon_record' => $marathonRecord,
            ];
        });
    }

    /**
     * API endpoint: returns competitors and rivals (loaded async by frontend)
     */
    public function profileExtras($mddId)
    {
        try {
            $cpmCompetitors = $this->getCachedCompetitors($mddId, 'cpm');
            $vq3Competitors = $this->getCachedCompetitors($mddId, 'vq3');
            $cpmRivals = $this->getCachedRivals($mddId, 'cpm');
            $vq3Rivals = $this->getCachedRivals($mddId, 'vq3');
        } catch (\Exception $e) {
            $cpmCompetitors = ['better' => [], 'worse' => [], 'my_score' => 0];
            $vq3Competitors = ['better' => [], 'worse' => [], 'my_score' => 0];
            $cpmRivals = ['beaten' => [], 'beaten_by' => []];
            $vq3Rivals = ['beaten' => [], 'beaten_by' => []];
        }

        return response()->json([
            'cpm_competitors' => $cpmCompetitors,
            'vq3_competitors' => $vq3Competitors,
            'cpm_rivals' => $cpmRivals,
            'vq3_rivals' => $vq3Rivals,
        ]);
    }

    /**
     * Get maps the user hasn't played yet (for completionist list)
     */
    protected function getUnplayedMaps($mddId, $page = 1) {
        // Get all map names the user has records on
        $playedMaps = Record::where('mdd_id', $mddId)
            ->whereNull('deleted_at')
            ->distinct('mapname')
            ->pluck('mapname')
            ->toArray();

        // Get all maps from the maps table that user hasn't played
        $unplayedMaps = DB::table('maps')
            ->whereNotIn('name', $playedMaps)
            ->orderBy('name', 'ASC')
            ->paginate(10, ['*'], 'unplayed_page', $page);

        return $unplayedMaps;
    }

    /**
     * Standalone progress bar view for overlays/embeds
     */
    public function progressBar($userId) {
        $user = User::query()
            ->where('id', $userId)
            ->first(['id', 'mdd_id', 'name']);

        if (!$user || !$user->mdd_id) {
            abort(404);
        }

        $totalMaps = DB::table('maps')->count();
        $playedMaps = Record::where('mdd_id', $user->mdd_id)
            ->whereNull('deleted_at')
            ->distinct('mapname')
            ->count('mapname');
        $unplayedMaps = $totalMaps - $playedMaps;

        return Inertia::render('ProfileProgressBar', [
            'user_name' => $user->name,
            'total_maps' => $totalMaps,
            'played_maps' => $playedMaps,
            'unplayed_maps' => $unplayedMaps,
        ]);
    }

    public function activityData(Request $request, $mddId)
    {
        $year = (int) $request->input('year', date('Y'));
        return response()->json([
            'activity_data' => $this->getActivityData($mddId, $year),
            'activity_year' => $year,
        ]);
    }

    private function getActivityData($mddId, $year)
    {
        return Cache::remember("activity:{$mddId}:{$year}:v2", 3600, function () use ($mddId, $year) {
            $rows = Record::where('mdd_id', $mddId)
                ->whereYear('date_set', $year)
                ->selectRaw('DATE(date_set) as date, physics, COUNT(*) as count')
                ->groupBy('date', 'physics')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                if (!isset($result[$row->date])) {
                    $result[$row->date] = ['vq3' => 0, 'cpm' => 0];
                }
                $result[$row->date][$row->physics] = $row->count;
            }

            return $result;
        });
    }

    private function getActivityYears($mddId)
    {
        return Cache::remember("activity_years:{$mddId}", 3600, function () use ($mddId) {
            return Record::where('mdd_id', $mddId)
                ->selectRaw('DISTINCT YEAR(date_set) as year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->toArray();
        });
    }

    private function getRenderStats($userId)
    {
        return Cache::remember("profile:render_stats:{$userId}", 3600, function () use ($userId) {
            $stats = DB::table('rendered_videos')
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->selectRaw('COUNT(*) as total_renders, COALESCE(SUM(render_duration_seconds), 0) as total_render_seconds')
                ->first();

            return [
                'total_renders' => (int) ($stats->total_renders ?? 0),
                'total_render_seconds' => (int) ($stats->total_render_seconds ?? 0),
            ];
        });
    }

    private function getLatestRenderedVideos($userId)
    {
        return RenderedVideo::where('user_id', $userId)
            ->completed()
            ->visible()
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get(['id', 'map_name', 'player_name', 'physics', 'time_ms', 'youtube_url', 'youtube_video_id', 'created_at']);
    }
}
