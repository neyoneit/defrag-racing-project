<?php

namespace App\Http\Controllers;

use App\Models\CommunityHelperScore;
use App\Models\CommunityTaskSession;
use App\Models\CommunityTaskVote;
use App\Models\Map;
use App\Models\MapDifficultyRating;
use App\Models\Record;
use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CommunityTasksController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $communityScore = CommunityHelperScore::where('user_id', $user->id)->first();

        return Inertia::render('CommunityTasks', [
            'assignmentTasks' => $this->generateAssignmentTasks($user, 3),
            'verificationTasks' => $this->generateVerificationTasks($user, 3),
            'difficultyTasks' => $this->generateDifficultyTasks($user, 6),
            'communityScore' => $communityScore,
            'tiers' => config('community-scores.tiers'),
            'weights' => config('community-scores.weights'),
            'personalBest' => $this->getPersonalBest($user->id),
            'leaderboard' => $this->getLeaderboard(),
        ]);
    }

    public function refresh()
    {
        $user = Auth::user();

        return response()->json([
            'assignmentTasks' => $this->generateAssignmentTasks($user, 3),
            'verificationTasks' => $this->generateVerificationTasks($user, 3),
            'difficultyTasks' => $this->generateDifficultyTasks($user, 6),
            'communityScore' => CommunityHelperScore::where('user_id', $user->id)->first(),
            'personalBest' => $this->getPersonalBest($user->id),
            'leaderboard' => $this->getLeaderboard(),
        ]);
    }

    /**
     * Record a vote for a demo task.
     */
    public function vote(Request $request)
    {
        $request->validate([
            'demo_id' => 'required|exists:uploaded_demos,id',
            'vote_type' => 'required|in:assign,not_sure,no_match,correct,unassign,better_match',
            'task_type' => 'required|in:assignment,verification',
            'selected_record_id' => 'nullable|exists:records,id',
        ]);

        $user = Auth::user();
        $demoId = $request->demo_id;
        $voteType = $request->vote_type;
        $taskType = $request->task_type;

        // Prevent duplicate votes on same demo
        $existing = CommunityTaskVote::where('user_id', $user->id)
            ->where('demo_id', $demoId)
            ->first();
        if ($existing) {
            return response()->json(['error' => 'Already voted on this demo'], 422);
        }

        // Create the vote
        $vote = CommunityTaskVote::create([
            'user_id' => $user->id,
            'demo_id' => $demoId,
            'vote_type' => $voteType,
            'task_type' => $taskType,
            'selected_record_id' => $request->selected_record_id,
        ]);

        $points = 0;
        $demo = UploadedDemo::find($demoId);

        // Handle immediate actions
        switch ($voteType) {
            case 'assign':
            case 'better_match':
                // Immediately assign the demo to the selected record
                if ($request->selected_record_id && $demo) {
                    $this->performAssignment($demo, $request->selected_record_id, $user);
                    $vote->update(['consensus_status' => 'archived']);
                }
                $points = 3;
                break;

            case 'correct':
                // Confirm existing assignment - archived immediately
                $vote->update(['consensus_status' => 'archived']);
                $points = 1;
                break;

            case 'unassign':
                // 1x unassign = immediate admin review
                $vote->update(['consensus_status' => 'needs_review']);
                $points = 1;
                break;

            case 'not_sure':
                // Check if 3x not_sure reached → needs admin review
                $notSureCount = CommunityTaskVote::where('demo_id', $demoId)
                    ->where('vote_type', 'not_sure')
                    ->count();
                if ($notSureCount >= 3) {
                    CommunityTaskVote::where('demo_id', $demoId)
                        ->where('vote_type', 'not_sure')
                        ->whereNull('consensus_status')
                        ->update(['consensus_status' => 'needs_review']);
                }
                $points = 1;
                break;

            case 'no_match':
                // Check if 3x no_match reached → needs admin review
                $noMatchCount = CommunityTaskVote::where('demo_id', $demoId)
                    ->where('vote_type', 'no_match')
                    ->count();
                if ($noMatchCount >= 3) {
                    CommunityTaskVote::where('demo_id', $demoId)
                        ->where('vote_type', 'no_match')
                        ->whereNull('consensus_status')
                        ->update(['consensus_status' => 'needs_review']);
                }
                $points = 1;
                break;
        }

        return response()->json([
            'success' => true,
            'points' => $points,
            'vote_type' => $voteType,
        ]);
    }

    private function performAssignment(UploadedDemo $demo, int $recordId, $user): void
    {
        $record = Record::find($recordId);
        if (!$record) return;

        $previousRecordId = $demo->record_id;

        if ($demo->offlineRecord) {
            $demo->offlineRecord->delete();
        }

        $demo->update([
            'record_id' => $recordId,
            'status' => 'assigned',
            'manually_assigned' => true,
        ]);

        RenderedVideo::where('demo_id', $demo->id)->update(['record_id' => $recordId]);

        \App\Models\DemoAssignmentReport::create([
            'demo_id' => $demo->id,
            'report_type' => 'manual_assign',
            'reported_by_user_id' => $user->id,
            'current_record_id' => $previousRecordId,
            'suggested_record_id' => $recordId,
            'reason_type' => 'community_task',
            'reason_details' => "Assigned via community tasks",
            'status' => 'resolved',
            'resolved_by_admin_id' => $user->id,
            'resolved_at' => now(),
        ]);

        Log::info('Community task assignment', [
            'demo_id' => $demo->id,
            'record_id' => $recordId,
            'by_user' => $user->id,
        ]);
    }

    public function saveSession(Request $request)
    {
        $request->validate([
            'points' => 'required|integer|min:0|max:1000',
            'assignments_completed' => 'required|integer|min:0|max:100',
            'ratings_completed' => 'required|integer|min:0|max:100',
        ]);

        $user = Auth::user();

        CommunityTaskSession::create([
            'user_id' => $user->id,
            'points' => $request->points,
            'assignments_completed' => $request->assignments_completed,
            'ratings_completed' => $request->ratings_completed,
        ]);

        return response()->json([
            'personalBest' => $this->getPersonalBest($user->id),
            'leaderboard' => $this->getLeaderboard(),
        ]);
    }

    public function fullLeaderboard()
    {
        return response()->json($this->getLeaderboard(100));
    }

    // ─── Task generation ───────────────────────────────────

    private function generateAssignmentTasks($user, int $count): array
    {
        // Get demo IDs this user already voted on
        $votedDemoIds = CommunityTaskVote::where('user_id', $user->id)->pluck('demo_id');
        $lockedDemoIds = $this->getLockedDemoIds($user->id);

        // Priority 1: Demos with "not_sure" votes that need more reviewers (re-review)
        $reReviewDemoIds = DB::table('community_task_votes')
            ->select('demo_id')
            ->where('vote_type', 'not_sure')
            ->whereNull('consensus_status')
            ->whereNotIn('demo_id', $votedDemoIds)
            ->whereNotIn('demo_id', $lockedDemoIds)
            ->groupBy('demo_id')
            ->havingRaw('COUNT(*) < 3')
            ->pluck('demo_id');

        // Priority 2: Fresh unassigned demos
        $freshDemos = UploadedDemo::query()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('record_id')
                       ->whereIn('status', ['processed']);
                })->orWhere('status', 'fallback-assigned');
            })
            ->where('time_ms', '>', 0)
            ->whereNotNull('map_name')
            ->whereNotNull('physics')
            ->whereNotIn('id', $votedDemoIds)
            ->whereNotIn('id', $lockedDemoIds)
            ->whereNotIn('id', $reReviewDemoIds)
            ->inRandomOrder()
            ->limit($count * 4)
            ->pluck('id');

        // Merge: re-review first, then fresh
        $candidateIds = $reReviewDemoIds->shuffle()->take($count)
            ->concat($freshDemos)
            ->unique()
            ->take($count * 4);

        $demos = UploadedDemo::whereIn('id', $candidateIds)->get()->shuffle();

        $tasks = [];
        foreach ($demos as $demo) {
            if (count($tasks) >= $count) break;

            $task = $this->buildDemoTask($demo, 'assignment');
            if ($task) {
                $task['is_re_review'] = $reReviewDemoIds->contains($demo->id);
                $tasks[] = $task;
            }
        }

        $this->lockDemos(collect($tasks)->pluck('demo.id')->toArray(), $user->id);

        return $tasks;
    }

    private function generateVerificationTasks($user, int $count): array
    {
        $votedDemoIds = CommunityTaskVote::where('user_id', $user->id)->pluck('demo_id');
        $lockedDemoIds = $this->getLockedDemoIds($user->id);

        $confirmedDemoIds = CommunityTaskVote::where('vote_type', 'correct')
            ->distinct()
            ->pluck('demo_id');

        $demos = UploadedDemo::query()
            ->whereNotNull('record_id')
            ->where('status', 'assigned')
            ->where('time_ms', '>', 0)
            ->whereNotNull('map_name')
            ->whereNotNull('physics')
            ->whereNotIn('id', $votedDemoIds)
            ->whereNotIn('id', $lockedDemoIds)
            ->whereNotIn('id', $confirmedDemoIds)
            ->where(function ($q) use ($user) {
                $q->where('user_id', '!=', $user->id)
                  ->orWhereNull('user_id');
            })
            ->with(['record.user'])
            ->inRandomOrder()
            ->limit($count * 4)
            ->get();

        $tasks = [];
        foreach ($demos as $demo) {
            if (count($tasks) >= $count) break;

            $task = $this->buildDemoTask($demo, 'verification');
            if ($task) {
                // Add current assignment info
                $task['current_record'] = $demo->record ? [
                    'id' => $demo->record->id,
                    'time' => $demo->record->time,
                    'player_name' => $demo->record->user?->name ?? $demo->record->name,
                    'rank' => $demo->record->rank,
                    'time_diff' => abs($demo->record->time - $demo->time_ms),
                    'youtube_url' => $demo->record->renderedVideos?->first()?->youtube_url,
                ] : null;
                $tasks[] = $task;
            }
        }

        $this->lockDemos(collect($tasks)->pluck('demo.id')->toArray(), $user->id);

        return $tasks;
    }

    private function buildDemoTask(UploadedDemo $demo, string $taskType): ?array
    {
        $gametype = $this->resolveGametype($demo);
        $records = Record::where('mapname', $demo->map_name)
            ->where('gametype', $gametype)
            ->whereNull('deleted_at')
            ->with(['user:id,name', 'uploadedDemos:id,record_id', 'renderedVideos:id,record_id,youtube_url,youtube_video_id'])
            ->orderBy('time')
            ->limit(100)
            ->get();

        if ($records->isEmpty()) return null;

        $map = Map::where('name', $demo->map_name)->first();

        return [
            'task_type' => $taskType,
            'demo' => [
                'id' => $demo->id,
                'map_name' => $demo->map_name,
                'player_name' => $demo->player_name,
                'physics' => strtoupper($demo->physics ?? 'VQ3'),
                'time_ms' => $demo->time_ms,
                'gametype' => $demo->gametype,
                'original_filename' => $demo->original_filename,
            ],
            'map_thumbnail' => $map?->thumbnail,
            'map_weapons' => $map?->weapons,
            'map_items' => $map?->items,
            'map_functions' => $map?->functions,
            'closest_matches' => $this->getClosestMatches($demo->time_ms, $records, 5),
            'all_records' => $records->map(fn ($r) => $this->mapRecord($r, $demo->time_ms))->values()->toArray(),
        ];
    }

    private function getClosestMatches(int $demoTime, $records, int $uniqueTimeCount): array
    {
        $sorted = $records->sortBy(fn ($r) => abs($r->time - $demoTime));

        $uniqueTimes = [];
        foreach ($sorted as $record) {
            $uniqueTimes[$record->time] = true;
            if (count($uniqueTimes) >= $uniqueTimeCount) break;
        }

        return $records->filter(fn ($r) => isset($uniqueTimes[$r->time]))
            ->sortBy(fn ($r) => [abs($r->time - $demoTime), $r->rank])
            ->values()
            ->map(fn ($r) => $this->mapRecord($r, $demoTime))
            ->toArray();
    }

    private function mapRecord($r, int $demoTime): array
    {
        $demo = $r->uploadedDemos->first();
        $video = $r->renderedVideos->first();

        return [
            'id' => $r->id,
            'time' => $r->time,
            'player_name' => $r->user?->name ?? $r->name,
            'rank' => $r->rank,
            'time_diff' => abs($r->time - $demoTime),
            'demo_id' => $demo?->id,
            'youtube_url' => $video?->youtube_url,
        ];
    }

    private function generateDifficultyTasks($user, int $count): array
    {
        $userMapNames = Record::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('mapname');

        $ratedMapIds = MapDifficultyRating::where('user_id', $user->id)->pluck('map_id');

        $maps = Map::whereIn('name', $userMapNames)
            ->whereNotIn('id', $ratedMapIds)
            ->whereNotNull('thumbnail')
            ->inRandomOrder()
            ->limit($count)
            ->get();

        if ($maps->count() < $count) {
            $remaining = $count - $maps->count();
            $existingIds = $maps->pluck('id')->merge($ratedMapIds);

            $extraMaps = Map::whereNotIn('id', $existingIds)
                ->whereNotNull('thumbnail')
                ->inRandomOrder()
                ->limit($remaining)
                ->get();

            $maps = $maps->concat($extraMaps);
        }

        return $maps->map(fn ($map) => [
            'id' => $map->id,
            'name' => $map->name,
            'thumbnail' => $map->thumbnail,
            'weapons' => $map->weapons,
            'items' => $map->items,
            'functions' => $map->functions,
        ])->values()->toArray();
    }

    // ─── Helpers ───────────────────────────────────────────

    private function getPersonalBest(int $userId): array
    {
        $best = CommunityTaskSession::where('user_id', $userId)
            ->orderByDesc('points')
            ->first();

        $totalSessions = CommunityTaskSession::where('user_id', $userId)->count();
        $totalPoints = CommunityTaskSession::where('user_id', $userId)->sum('points');

        return [
            'best_points' => $best?->points ?? 0,
            'best_date' => $best?->created_at?->toIso8601String(),
            'total_sessions' => $totalSessions,
            'total_points' => (int) $totalPoints,
        ];
    }

    private function getLeaderboard(int $limit = 50): array
    {
        $entries = DB::select("
            SELECT
                s.user_id,
                u.name,
                u.profile_photo_path,
                u.country,
                MAX(s.points) as best_points,
                COUNT(s.id) as total_sessions,
                SUM(s.points) as total_points
            FROM community_task_sessions s
            JOIN users u ON u.id = s.user_id
            GROUP BY s.user_id, u.name, u.profile_photo_path, u.country
            ORDER BY best_points DESC, total_points DESC
            LIMIT ?
        ", [$limit]);

        $rank = 0;
        return array_map(function ($entry) use (&$rank) {
            $rank++;
            return [
                'rank' => $rank,
                'user_id' => $entry->user_id,
                'name' => $entry->name,
                'profile_photo_path' => $entry->profile_photo_path,
                'country' => $entry->country,
                'best_points' => (int) $entry->best_points,
                'total_sessions' => (int) $entry->total_sessions,
                'total_points' => (int) $entry->total_points,
            ];
        }, $entries);
    }

    private function getLockedDemoIds(int $excludeUserId): array
    {
        $locks = Cache::get('community_task_locks', []);
        $now = now()->timestamp;

        // Clean expired locks
        $active = collect($locks)->filter(fn ($data) => $data['expires'] > $now && $data['user'] !== $excludeUserId);

        return $active->keys()->map(fn ($k) => (int) $k)->toArray();
    }

    private function lockDemos(array $demoIds, int $userId): void
    {
        $locks = Cache::get('community_task_locks', []);
        $now = now()->timestamp;

        // Clean expired
        $locks = collect($locks)->filter(fn ($data) => $data['expires'] > $now)->toArray();

        // Add new locks
        foreach ($demoIds as $id) {
            $locks[$id] = ['user' => $userId, 'expires' => now()->addMinutes(30)->timestamp];
        }

        Cache::put('community_task_locks', $locks, now()->addHours(2));
    }

    private function resolveGametype(UploadedDemo $demo): string
    {
        if ($demo->gametype && str_starts_with($demo->gametype, 'run_')) {
            return $demo->gametype;
        }
        return 'run_' . strtolower($demo->physics ?? 'vq3');
    }
}
