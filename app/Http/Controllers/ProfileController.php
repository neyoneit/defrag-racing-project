<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\User;
use App\Models\Record;
use App\Models\MddProfile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProfileController extends Controller {
    public function index(Request $request, $userId) {
        $totalStart = microtime(true);
        $timings = [];

        $start = microtime(true);
        $user = User::query()
            ->where('id', $userId)
            ->with('clan')
            ->first(['id', 'mdd_id', 'name', 'profile_photo_path', 'profile_background_path', 'country', 'color', 'avatar_effect', 'name_effect', 'avatar_border_color', 'discord_name', 'twitch_name', 'twitter_name']);
        $timings['user_query'] = round((microtime(true) - $start) * 1000, 2);

        if (! $user) {
            return redirect()->route('profile.mdd', $userId);
        }

        if (! $user->mdd_id ) {
            return Inertia::render('Profile')
                ->with('user', $user)
                ->with('vq3Records', (object)['total' => 0, 'data' => [], 'per_page' => 20])
                ->with('cpmRecords', (object)['total' => 0, 'data' => [], 'per_page' => 20])
                ->with('hasProfile', false);
        }

        $start = microtime(true);
        $worldRecordsCpm = Record::where('mdd_id', $user->mdd_id)->where('rank', 1)->where('physics', 'cpm')->count();
        $worldRecordsVq3 = Record::where('mdd_id', $user->mdd_id)->where('rank', 1)->where('physics', 'vq3')->count();

        // Total records count
        $totalRecordsCpm = Record::where('mdd_id', $user->mdd_id)->where('physics', 'cpm')->count();
        $totalRecordsVq3 = Record::where('mdd_id', $user->mdd_id)->where('physics', 'vq3')->count();

        // Top 3 positions count
        $top3Cpm = Record::where('mdd_id', $user->mdd_id)->where('physics', 'cpm')->whereBetween('rank', [1, 3])->count();
        $top3Vq3 = Record::where('mdd_id', $user->mdd_id)->where('physics', 'vq3')->whereBetween('rank', [1, 3])->count();

        // Top 10 positions count
        $top10Cpm = Record::where('mdd_id', $user->mdd_id)->where('physics', 'cpm')->whereBetween('rank', [1, 10])->count();
        $top10Vq3 = Record::where('mdd_id', $user->mdd_id)->where('physics', 'vq3')->whereBetween('rank', [1, 10])->count();
        $timings['basic_stats'] = round((microtime(true) - $start) * 1000, 2);

        // Map feature stats - join with maps table to check features
        $start = microtime(true);
        // Slick records
        $slickCpm = Record::where('records.mdd_id', $user->mdd_id)
            ->where('records.physics', 'cpm')
            ->join('maps', 'records.mapname', '=', 'maps.name')
            ->where('maps.functions', 'LIKE', '%slick%')
            ->count();
        $slickVq3 = Record::where('records.mdd_id', $user->mdd_id)
            ->where('records.physics', 'vq3')
            ->join('maps', 'records.mapname', '=', 'maps.name')
            ->where('maps.functions', 'LIKE', '%slick%')
            ->count();

        // Jumppad records
        $jumppadCpm = Record::where('records.mdd_id', $user->mdd_id)
            ->where('records.physics', 'cpm')
            ->join('maps', 'records.mapname', '=', 'maps.name')
            ->where('maps.functions', 'LIKE', '%jumppad%')
            ->count();
        $jumppadVq3 = Record::where('records.mdd_id', $user->mdd_id)
            ->where('records.physics', 'vq3')
            ->join('maps', 'records.mapname', '=', 'maps.name')
            ->where('maps.functions', 'LIKE', '%jumppad%')
            ->count();

        // Teleporter records
        $teleporterCpm = Record::where('records.mdd_id', $user->mdd_id)
            ->where('records.physics', 'cpm')
            ->join('maps', 'records.mapname', '=', 'maps.name')
            ->where('maps.functions', 'LIKE', '%tele%')
            ->count();
        $teleporterVq3 = Record::where('records.mdd_id', $user->mdd_id)
            ->where('records.physics', 'vq3')
            ->join('maps', 'records.mapname', '=', 'maps.name')
            ->where('maps.functions', 'LIKE', '%tele%')
            ->count();
        $timings['map_features'] = round((microtime(true) - $start) * 1000, 2);

        // Activity streak - longest consecutive days with records
        $start = microtime(true);
        $longestStreak = DB::select("
            SELECT MAX(streak) as longest_streak
            FROM (
                SELECT
                    @streak := IF(@prev_date = DATE(date_set) - INTERVAL 1 DAY, @streak + 1, 1) as streak,
                    @prev_date := DATE(date_set) as date_set
                FROM records, (SELECT @streak := 0, @prev_date := NULL) vars
                WHERE mdd_id = ?
                ORDER BY date_set
            ) streaks
        ", [$user->mdd_id]);
        $longestStreakDays = $longestStreak[0]->longest_streak ?? 0;
        $timings['activity_streak'] = round((microtime(true) - $start) * 1000, 2);

        // Unique maps played
        $start = microtime(true);
        $uniqueMapsCpm = Record::where('mdd_id', $user->mdd_id)->where('physics', 'cpm')->distinct('mapname')->count('mapname');
        $uniqueMapsVq3 = Record::where('mdd_id', $user->mdd_id)->where('physics', 'vq3')->distinct('mapname')->count('mapname');

        // Average Rank - Overall average position across all records
        $avgRankCpm = Record::where('mdd_id', $user->mdd_id)->where('physics', 'cpm')->avg('rank');
        $avgRankVq3 = Record::where('mdd_id', $user->mdd_id)->where('physics', 'vq3')->avg('rank');

        // Dominance Score - % of unique maps where you hold #1
        $dominanceCpm = $uniqueMapsCpm > 0 ? round(($worldRecordsCpm / $uniqueMapsCpm) * 100, 1) : 0;
        $dominanceVq3 = $uniqueMapsVq3 > 0 ? round(($worldRecordsVq3 / $uniqueMapsVq3) * 100, 1) : 0;

        // First Record Date - Defrag journey start
        $firstRecord = Record::where('mdd_id', $user->mdd_id)->orderBy('date_set', 'ASC')->first(['date_set']);
        $firstRecordDate = $firstRecord ? $firstRecord->date_set : null;

        // Most Active Month/Year - When most records were set
        $mostActiveMonth = DB::select("
            SELECT DATE_FORMAT(date_set, '%Y-%m') as month, COUNT(*) as count
            FROM records
            WHERE mdd_id = ?
            GROUP BY month
            ORDER BY count DESC
            LIMIT 1
        ", [$user->mdd_id]);
        $mostActiveMonthData = $mostActiveMonth[0] ?? null;

        // Weapon Specialist - Which weapon type you dominate most (by WR count)
        $weaponStats = [];
        $weapons = ['rocket', 'plasma', 'grenade', 'bfg', 'mg', 'sg', 'lg', 'rg'];
        foreach ($weapons as $weapon) {
            $weaponStats[$weapon] = Record::where('mdd_id', $user->mdd_id)
                ->where('mode', 'LIKE', "%{$weapon}%")
                ->where('rank', 1)
                ->count();
        }
        arsort($weaponStats);
        $weaponSpecialist = array_key_first($weaponStats);
        $weaponSpecialistCount = $weaponStats[$weaponSpecialist] ?? 0;

        // Marathon Runner - Longest time record set
        $marathonRecord = Record::where('mdd_id', $user->mdd_id)
            ->orderBy('time', 'DESC')
            ->first(['time', 'mapname', 'physics', 'mode']);
        $timings['additional_stats'] = round((microtime(true) - $start) * 1000, 2);

        $type = $request->input('type', 'latest');
        $mode = $request->input('mode', 'all');

        $types = ['recentlybeaten', 'tiedranks', 'bestranks', 'besttimes', 'worstranks', 'worsttimes', 'untouchable'];

        if (!in_array($type, $types)) {
            $type = 'latest';
        }

        $baseRecords = match ($type) {
            'recentlybeaten'    => $this->recentlyBeaten($user->mdd_id),
            'tiedranks'         => $this->tiedRanks($user->mdd_id),
            'bestranks'         => $this->bestRanks($user->mdd_id),
            'besttimes'         => $this->bestTimes($user->mdd_id),
            'worstranks'        => $this->worstRanks($user->mdd_id),
            'worsttimes'        => $this->worstTimes($user->mdd_id),
            'untouchable'       => $this->untouchableWRs($user->mdd_id),
            default             => $this->latestRecords($user->mdd_id),
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
        $start = microtime(true);
        $vq3Records = clone $baseRecords;
        $vq3Records = $vq3Records->where($tablePrefix . '.physics', 'vq3');

        // Only eager load 'map' if not using table aliases (to avoid soft delete conflicts)
        if ($tablePrefix === 'records') {
            $vq3Records = $vq3Records->with('map');
        }
        $vq3Records = $vq3Records->paginate(20, ['*'], 'vq3_page')->withQueryString();
        $timings['vq3_records'] = round((microtime(true) - $start) * 1000, 2);

        // Get CPM records (20 per page)
        $start = microtime(true);
        $cpmRecords = clone $baseRecords;
        $cpmRecords = $cpmRecords->where($tablePrefix . '.physics', 'cpm');

        // Only eager load 'map' if not using table aliases (to avoid soft delete conflicts)
        if ($tablePrefix === 'records') {
            $cpmRecords = $cpmRecords->with('map');
        }
        $cpmRecords = $cpmRecords->paginate(20, ['*'], 'cpm_page')->withQueryString();
        $timings['cpm_records'] = round((microtime(true) - $start) * 1000, 2);

        // Merge profile data with additional stats
        $profileData = $user->mdd_profile;
        if ($profileData) {
            $profileData->cpm_records = $totalRecordsCpm;
            $profileData->vq3_records = $totalRecordsVq3;
            $profileData->cpm_top3 = $top3Cpm;
            $profileData->vq3_top3 = $top3Vq3;
            $profileData->cpm_top10 = $top10Cpm;
            $profileData->vq3_top10 = $top10Vq3;
            $profileData->cpm_slick = $slickCpm;
            $profileData->vq3_slick = $slickVq3;
            $profileData->cpm_jumppad = $jumppadCpm;
            $profileData->vq3_jumppad = $jumppadVq3;
            $profileData->cpm_teleporter = $teleporterCpm;
            $profileData->vq3_teleporter = $teleporterVq3;
            $profileData->cpm_unique_maps = $uniqueMapsCpm;
            $profileData->vq3_unique_maps = $uniqueMapsVq3;
            $profileData->longest_streak = $longestStreakDays;
            $profileData->cpm_avg_rank = $avgRankCpm ? round($avgRankCpm, 1) : 0;
            $profileData->vq3_avg_rank = $avgRankVq3 ? round($avgRankVq3, 1) : 0;
            $profileData->cpm_dominance = $dominanceCpm;
            $profileData->vq3_dominance = $dominanceVq3;
            $profileData->first_record_date = $firstRecordDate;
            $profileData->most_active_month = $mostActiveMonthData;
            $profileData->weapon_specialist = $weaponSpecialist;
            $profileData->weapon_specialist_count = $weaponSpecialistCount;
            $profileData->marathon_record = $marathonRecord;
        }

        // Get competitors and rivals (with error handling)
        $start = microtime(true);
        try {
            $start_cpm_comp = microtime(true);
            $cpmCompetitors = $this->getCachedCompetitors($user->mdd_id, 'cpm');
            $timings['cpm_competitors_ms'] = round((microtime(true) - $start_cpm_comp) * 1000, 2);

            $start_vq3_comp = microtime(true);
            $vq3Competitors = $this->getCachedCompetitors($user->mdd_id, 'vq3');
            $timings['vq3_competitors_ms'] = round((microtime(true) - $start_vq3_comp) * 1000, 2);

            $start_cpm_rivals = microtime(true);
            $cpmRivals = $this->getCachedRivals($user->mdd_id, 'cpm');
            $timings['cpm_rivals_ms'] = round((microtime(true) - $start_cpm_rivals) * 1000, 2);

            $start_vq3_rivals = microtime(true);
            $vq3Rivals = $this->getCachedRivals($user->mdd_id, 'vq3');
            $timings['vq3_rivals_ms'] = round((microtime(true) - $start_vq3_rivals) * 1000, 2);

            $timings['competitors_rivals'] = round((microtime(true) - $start) * 1000, 2);

            // Debug logging
            \Log::info('Competitors/Rivals loaded', [
                'cpm_comp_better' => count($cpmCompetitors['better']),
                'cpm_comp_worse' => count($cpmCompetitors['worse']),
                'cpm_rivals_beaten' => count($cpmRivals['beaten']),
                'cpm_rivals_beaten_by' => count($cpmRivals['beaten_by']),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading competitors/rivals: ' . $e->getMessage());
            $cpmCompetitors = ['better' => [], 'worse' => [], 'my_score' => 0];
            $vq3Competitors = ['better' => [], 'worse' => [], 'my_score' => 0];
            $cpmRivals = ['beaten' => [], 'beaten_by' => []];
            $vq3Rivals = ['beaten' => [], 'beaten_by' => []];
            $timings['competitors_rivals'] = round((microtime(true) - $start) * 1000, 2);
        }

        // Get unplayed maps for completionist list
        $start = microtime(true);
        $unplayedMaps = $this->getUnplayedMaps($user->mdd_id, $request->input('unplayed_page', 1));
        $totalMaps = DB::table('maps')->count();
        $timings['unplayed_maps'] = round((microtime(true) - $start) * 1000, 2);

        \Log::info('Unplayed maps data', [
            'total' => $unplayedMaps->total(),
            'per_page' => $unplayedMaps->perPage(),
            'current_page' => $unplayedMaps->currentPage(),
            'count' => count($unplayedMaps->items())
        ]);

        $timings['total'] = round((microtime(true) - $totalStart) * 1000, 2);

        \Log::info('Profile page load times', $timings);

        return Inertia::render('Profile')
            ->with('vq3Records', $vq3Records)
            ->with('cpmRecords', $cpmRecords)
            ->with('user', $user)
            ->with('type', $type)
            ->with('cpm_world_records', $worldRecordsCpm)
            ->with('vq3_world_records', $worldRecordsVq3)
            ->with('profile', $profileData)
            ->with('cpm_competitors', $cpmCompetitors)
            ->with('vq3_competitors', $vq3Competitors)
            ->with('cpm_rivals', $cpmRivals)
            ->with('vq3_rivals', $vq3Rivals)
            ->with('unplayed_maps', $unplayedMaps)
            ->with('total_maps', $totalMaps)
            ->with('load_times', $timings)
            ->with('hasProfile', true);
    }

    public function mdd(Request $request, $userId) {
        $user = MddProfile::where('id', $userId)->with('user')->first();

        if (! $user) {
            return redirect()->route('home');
        }

        $worldRecordsCpm = Record::where('mdd_id', $user->id)->where('rank', 1)->where('physics', 'cpm')->count();
        $worldRecordsVq3 = Record::where('mdd_id', $user->id)->where('rank', 1)->where('physics', 'vq3')->count();

        // Total records count
        $totalRecordsCpm = Record::where('mdd_id', $user->id)->where('physics', 'cpm')->count();
        $totalRecordsVq3 = Record::where('mdd_id', $user->id)->where('physics', 'vq3')->count();

        // Top 3 positions count
        $top3Cpm = Record::where('mdd_id', $user->id)->where('physics', 'cpm')->whereBetween('rank', [1, 3])->count();
        $top3Vq3 = Record::where('mdd_id', $user->id)->where('physics', 'vq3')->whereBetween('rank', [1, 3])->count();

        // Top 10 positions count
        $top10Cpm = Record::where('mdd_id', $user->id)->where('physics', 'cpm')->whereBetween('rank', [1, 10])->count();
        $top10Vq3 = Record::where('mdd_id', $user->id)->where('physics', 'vq3')->whereBetween('rank', [1, 10])->count();

        // Map feature stats
        $slickCpm = Record::where('records.mdd_id', $user->id)->where('records.physics', 'cpm')->join('maps', 'records.mapname', '=', 'maps.name')->where('maps.functions', 'LIKE', '%slick%')->count();
        $slickVq3 = Record::where('records.mdd_id', $user->id)->where('records.physics', 'vq3')->join('maps', 'records.mapname', '=', 'maps.name')->where('maps.functions', 'LIKE', '%slick%')->count();
        $jumppadCpm = Record::where('records.mdd_id', $user->id)->where('records.physics', 'cpm')->join('maps', 'records.mapname', '=', 'maps.name')->where('maps.functions', 'LIKE', '%jumppad%')->count();
        $jumppadVq3 = Record::where('records.mdd_id', $user->id)->where('records.physics', 'vq3')->join('maps', 'records.mapname', '=', 'maps.name')->where('maps.functions', 'LIKE', '%jumppad%')->count();
        $teleporterCpm = Record::where('records.mdd_id', $user->id)->where('records.physics', 'cpm')->join('maps', 'records.mapname', '=', 'maps.name')->where('maps.functions', 'LIKE', '%tele%')->count();
        $teleporterVq3 = Record::where('records.mdd_id', $user->id)->where('records.physics', 'vq3')->join('maps', 'records.mapname', '=', 'maps.name')->where('maps.functions', 'LIKE', '%tele%')->count();
        $longestStreak = DB::select("SELECT MAX(streak) as longest_streak FROM (SELECT @streak := IF(@prev_date = DATE(date_set) - INTERVAL 1 DAY, @streak + 1, 1) as streak, @prev_date := DATE(date_set) as date_set FROM records, (SELECT @streak := 0, @prev_date := NULL) vars WHERE mdd_id = ? ORDER BY date_set) streaks", [$user->id]);
        $longestStreakDays = $longestStreak[0]->longest_streak ?? 0;
        $uniqueMapsCpm = Record::where('mdd_id', $user->id)->where('physics', 'cpm')->distinct('mapname')->count('mapname');
        $uniqueMapsVq3 = Record::where('mdd_id', $user->id)->where('physics', 'vq3')->distinct('mapname')->count('mapname');

        // Average Rank
        $avgRankCpm = Record::where('mdd_id', $user->id)->where('physics', 'cpm')->avg('rank');
        $avgRankVq3 = Record::where('mdd_id', $user->id)->where('physics', 'vq3')->avg('rank');

        // Dominance Score
        $dominanceCpm = $uniqueMapsCpm > 0 ? round(($worldRecordsCpm / $uniqueMapsCpm) * 100, 1) : 0;
        $dominanceVq3 = $uniqueMapsVq3 > 0 ? round(($worldRecordsVq3 / $uniqueMapsVq3) * 100, 1) : 0;

        // First Record Date
        $firstRecord = Record::where('mdd_id', $user->id)->orderBy('date_set', 'ASC')->first(['date_set']);
        $firstRecordDate = $firstRecord ? $firstRecord->date_set : null;

        // Most Active Month/Year
        $mostActiveMonth = DB::select("SELECT DATE_FORMAT(date_set, '%Y-%m') as month, COUNT(*) as count FROM records WHERE mdd_id = ? GROUP BY month ORDER BY count DESC LIMIT 1", [$user->id]);
        $mostActiveMonthData = $mostActiveMonth[0] ?? null;

        // Weapon Specialist
        $weaponStats = [];
        $weapons = ['rocket', 'plasma', 'grenade', 'bfg', 'mg', 'sg', 'lg', 'rg'];
        foreach ($weapons as $weapon) {
            $weaponStats[$weapon] = Record::where('mdd_id', $user->id)->where('mode', 'LIKE', "%{$weapon}%")->where('rank', 1)->count();
        }
        arsort($weaponStats);
        $weaponSpecialist = array_key_first($weaponStats);
        $weaponSpecialistCount = $weaponStats[$weaponSpecialist] ?? 0;

        // Marathon Runner
        $marathonRecord = Record::where('mdd_id', $user->id)->orderBy('time', 'DESC')->first(['time', 'mapname', 'physics', 'mode']);

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
            $vq3Records = $vq3Records->with('map');
        }
        $vq3Records = $vq3Records->paginate(20, ['*'], 'vq3_page')->withQueryString();

        // Get CPM records (20 per page)
        $cpmRecords = clone $baseRecords;
        $cpmRecords = $cpmRecords->where($tablePrefix . '.physics', 'cpm');

        // Only eager load 'map' if not using table aliases (to avoid soft delete conflicts)
        if ($tablePrefix === 'records') {
            $cpmRecords = $cpmRecords->with('map');
        }
        $cpmRecords = $cpmRecords->paginate(20, ['*'], 'cpm_page')->withQueryString();

        // Add additional stats to profile data
        $user->cpm_records = $totalRecordsCpm;
        $user->vq3_records = $totalRecordsVq3;
        $user->cpm_top3 = $top3Cpm;
        $user->vq3_top3 = $top3Vq3;
        $user->cpm_top10 = $top10Cpm;
        $user->vq3_top10 = $top10Vq3;
        $user->cpm_slick = $slickCpm;
        $user->vq3_slick = $slickVq3;
        $user->cpm_jumppad = $jumppadCpm;
        $user->vq3_jumppad = $jumppadVq3;
        $user->cpm_teleporter = $teleporterCpm;
        $user->vq3_teleporter = $teleporterVq3;
        $user->cpm_unique_maps = $uniqueMapsCpm;
        $user->vq3_unique_maps = $uniqueMapsVq3;
        $user->longest_streak = $longestStreakDays;
        $user->cpm_avg_rank = $avgRankCpm ? round($avgRankCpm, 1) : 0;
        $user->vq3_avg_rank = $avgRankVq3 ? round($avgRankVq3, 1) : 0;
        $user->cpm_dominance = $dominanceCpm;
        $user->vq3_dominance = $dominanceVq3;
        $user->first_record_date = $firstRecordDate;
        $user->most_active_month = $mostActiveMonthData;
        $user->weapon_specialist = $weaponSpecialist;
        $user->weapon_specialist_count = $weaponSpecialistCount;
        $user->marathon_record = $marathonRecord;

        // Get competitors and rivals (with error handling)
        try {
            $cpmCompetitors = $this->getCachedCompetitors($user->id, 'cpm');
            $vq3Competitors = $this->getCachedCompetitors($user->id, 'vq3');
            $cpmRivals = $this->getCachedRivals($user->id, 'cpm');
            $vq3Rivals = $this->getCachedRivals($user->id, 'vq3');
        } catch (\Exception $e) {
            $cpmCompetitors = ['better' => [], 'worse' => [], 'my_score' => 0];
            $vq3Competitors = ['better' => [], 'worse' => [], 'my_score' => 0];
            $cpmRivals = ['beaten' => [], 'beaten_by' => []];
            $vq3Rivals = ['beaten' => [], 'beaten_by' => []];
        }

        // Get unplayed maps for completionist list
        $unplayedMaps = $this->getUnplayedMaps($user->id, $request->input('unplayed_page', 1));
        $totalMaps = DB::table('maps')->count();

        return Inertia::render('Profile')
            ->with('vq3Records', $vq3Records)
            ->with('cpmRecords', $cpmRecords)
            ->with('user', $user->user)
            ->with('type', $type)
            ->with('cpm_world_records', $worldRecordsCpm)
            ->with('vq3_world_records', $worldRecordsVq3)
            ->with('profile', $user)
            ->with('cpm_competitors', $cpmCompetitors)
            ->with('vq3_competitors', $vq3Competitors)
            ->with('cpm_rivals', $cpmRivals)
            ->with('vq3_rivals', $vq3Rivals)
            ->with('unplayed_maps', $unplayedMaps)
            ->with('total_maps', $totalMaps)
            ->with('hasProfile', true);
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
        Cache::forget("profile:competitors:{$mddId}:cpm");
        Cache::forget("profile:competitors:{$mddId}:vq3");
        Cache::forget("profile:rivals:{$mddId}:cpm");
        Cache::forget("profile:rivals:{$mddId}:vq3");
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
}
