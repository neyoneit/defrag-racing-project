<?php

namespace App\Http\Controllers;

use App\Models\CommunityHelperScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CommunityLeaderboardController extends Controller
{
    const PAGINATION_LIMIT = 50;
    const CACHE_TTL = 2100; // 35 minutes (scores recalculate every 30 min)

    public function index(Request $request)
    {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;
        $page = max(1, (int) $request->input('page', 1));

        $myScore = null;
        if (auth()->check()) {
            $myScore = CommunityHelperScore::where('user_id', auth()->id())
                ->first();
        }

        if (!$isPartial) {
            return Inertia::render('CommunityLeaderboard', [
                'scores' => null,
                'myScore' => $myScore,
                'tiers' => config('community-scores.tiers'),
                'weights' => config('community-scores.weights'),
            ]);
        }

        $cacheKey = "community:leaderboard:page:{$page}";
        $scores = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($page) {
            return CommunityHelperScore::with('user:id,name,profile_photo_path,country,avatar_effect,name_effect,color,avatar_border_color')
                ->where('total_score', '>', 0)
                ->orderBy('rank')
                ->paginate(self::PAGINATION_LIMIT, ['*'], 'page', $page);
        });

        return Inertia::render('CommunityLeaderboard', [
            'scores' => $scores,
            'myScore' => $myScore,
            'tiers' => config('community-scores.tiers'),
            'weights' => config('community-scores.weights'),
        ]);
    }
}
