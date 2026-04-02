<?php

namespace App\Services;

use App\Models\CommunityHelperScore;
use App\Models\MapperClaim;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommunityScoreService
{
    private array $weights;

    public function __construct()
    {
        $this->weights = config('community-scores.weights');
    }

    public function calculateAll(): void
    {
        $startTime = microtime(true);
        Log::info('Community scores: starting calculation');

        $scores = collect();

        // Gather all category counts
        $this->addDemosUploaded($scores);
        $this->addTagsAdded($scores);
        $this->addAliasReports($scores);
        $this->addDemoAssignmentReports($scores);
        $this->addMaplistsCreated($scores);
        $this->addMaplistMapsAdded($scores);
        $this->addMaplistLikesReceived($scores);
        $this->addMaplistFavoritesReceived($scores);
        $this->addPlayLaterMaps($scores);
        $this->addMarketplaceListings($scores);
        $this->addMarketplaceReviewsWritten($scores);
        $this->addMarketplaceReviewsReceived($scores);
        $this->addHeadhunterCreated($scores);
        $this->addHeadhunterCompleted($scores);
        $this->addRecordFlags($scores);
        $this->addModelsUploaded($scores);
        $this->addRenderRequests($scores);
        $this->addClanCreated($scores);
        $this->addClanMembership($scores);
        $this->addNsfwFlags($scores);
        $this->addWikiEdits($scores);
        $this->addCommunityTasksCompleted($scores);
        $this->addDifficultyRatings($scores);
        $this->addRecordsCount($scores);
        $this->addMapsAuthored($scores);
        $this->addModelsAuthored($scores);
        $this->addProfileData($scores);
        $this->addDonations($scores);

        // Calculate total scores and ranks
        $this->computeTotalScores($scores);
        $this->assignRanks($scores);

        // Bulk upsert
        $this->persistScores($scores);

        $duration = round(microtime(true) - $startTime, 2);
        Log::info("Community scores: completed in {$duration}s for {$scores->count()} users");
    }

    private function ensureUser(Collection &$scores, int $userId): void
    {
        if (!$scores->has($userId)) {
            $scores->put($userId, [
                'user_id' => $userId,
                'demos_uploaded' => 0,
                'tags_added' => 0,
                'alias_reports' => 0,
                'demo_assignment_reports' => 0,
                'maplists_created' => 0,
                'maplist_maps_added' => 0,
                'maplist_likes_received' => 0,
                'maplist_favorites_received' => 0,
                'play_later_maps' => 0,
                'marketplace_listings' => 0,
                'marketplace_reviews_written' => 0,
                'marketplace_reviews_received' => 0,
                'headhunter_created' => 0,
                'headhunter_completed' => 0,
                'record_flags' => 0,
                'models_uploaded' => 0,
                'render_requests' => 0,
                'clan_created' => 0,
                'clan_membership' => false,
                'nsfw_flags' => 0,
                'wiki_edits' => 0,
                'community_tasks_completed' => 0,
                'difficulty_ratings' => 0,
                'records_count' => 0,
                'maps_authored' => 0,
                'models_authored' => 0,
                'social_connections' => 0,
                'profile_avatar' => false,
                'profile_background' => false,
                'profile_layout_customized' => false,
                'name_effect_set' => false,
                'avatar_effect_set' => false,
                'donation_total_eur' => 0,
                'total_score' => 0,
                'community_badge_score' => 0,
                'rank' => 0,
            ]);
        }
    }

    private function mergeGroupedCounts(Collection &$scores, string $field, Collection $counts): void
    {
        foreach ($counts as $row) {
            $this->ensureUser($scores, $row->user_id);
            $data = $scores->get($row->user_id);
            $data[$field] = (int) $row->cnt;
            $scores->put($row->user_id, $data);
        }
    }

    private function addDemosUploaded(Collection &$scores): void
    {
        $counts = DB::table('uploaded_demos')
            ->whereNotNull('user_id')
            ->whereIn('status', ['assigned', 'fallback-assigned', 'processed', 'unsupported-version', 'failed', 'failed-validity'])
            ->where('created_at', '>=', config('community-scores.demos_uploaded_after', '2026-03-14'))
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'demos_uploaded', $counts);
    }

    private function addTagsAdded(Collection &$scores): void
    {
        $counts = DB::table('map_tag')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'tags_added', $counts);
    }

    private function addAliasReports(Collection &$scores): void
    {
        $counts = DB::table('alias_reports')
            ->where('status', 'resolved')
            ->groupBy('reported_by_user_id')
            ->select('reported_by_user_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'alias_reports', $counts);
    }

    private function addDemoAssignmentReports(Collection &$scores): void
    {
        $counts = DB::table('uploaded_demos')
            ->where('manually_assigned', true)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'demo_assignment_reports', $counts);
    }

    private function addMaplistsCreated(Collection &$scores): void
    {
        $counts = DB::table('maplists')
            ->where('is_public', true)
            ->where('is_play_later', false)
            ->where('is_draft', false)
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'maplists_created', $counts);
    }

    private function addMaplistMapsAdded(Collection &$scores): void
    {
        $counts = DB::table('maplist_maps')
            ->join('maplists', 'maplists.id', '=', 'maplist_maps.maplist_id')
            ->where('maplists.is_public', true)
            ->where('maplists.is_play_later', false)
            ->where('maplists.is_draft', false)
            ->groupBy('maplists.user_id')
            ->select('maplists.user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'maplist_maps_added', $counts);
    }

    private function addMaplistLikesReceived(Collection &$scores): void
    {
        $counts = DB::table('maplist_likes')
            ->join('maplists', 'maplists.id', '=', 'maplist_likes.maplist_id')
            ->where('maplists.is_public', true)
            ->where('maplists.is_play_later', false)
            ->where('maplists.is_draft', false)
            ->groupBy('maplists.user_id')
            ->select('maplists.user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'maplist_likes_received', $counts);
    }

    private function addMaplistFavoritesReceived(Collection &$scores): void
    {
        $counts = DB::table('maplist_favorites')
            ->join('maplists', 'maplists.id', '=', 'maplist_favorites.maplist_id')
            ->where('maplists.is_public', true)
            ->where('maplists.is_play_later', false)
            ->where('maplists.is_draft', false)
            ->groupBy('maplists.user_id')
            ->select('maplists.user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'maplist_favorites_received', $counts);
    }

    private function addPlayLaterMaps(Collection &$scores): void
    {
        $counts = DB::table('maplist_maps')
            ->join('maplists', 'maplists.id', '=', 'maplist_maps.maplist_id')
            ->where('maplists.is_play_later', true)
            ->groupBy('maplists.user_id')
            ->select('maplists.user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'play_later_maps', $counts);
    }

    private function addMarketplaceListings(Collection &$scores): void
    {
        $counts = DB::table('marketplace_listings')
            ->whereNull('deleted_at')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'marketplace_listings', $counts);
    }

    private function addMarketplaceReviewsWritten(Collection &$scores): void
    {
        $counts = DB::table('marketplace_reviews')
            ->groupBy('reviewer_id')
            ->select('reviewer_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'marketplace_reviews_written', $counts);
    }

    private function addMarketplaceReviewsReceived(Collection &$scores): void
    {
        $counts = DB::table('marketplace_reviews')
            ->groupBy('reviewee_id')
            ->select('reviewee_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'marketplace_reviews_received', $counts);
    }

    private function addHeadhunterCreated(Collection &$scores): void
    {
        $counts = DB::table('headhunter_challenges')
            ->whereNull('deleted_at')
            ->groupBy('creator_id')
            ->select('creator_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'headhunter_created', $counts);
    }

    private function addHeadhunterCompleted(Collection &$scores): void
    {
        $counts = DB::table('challenge_participants')
            ->where('status', 'approved')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'headhunter_completed', $counts);
    }

    private function addRecordFlags(Collection &$scores): void
    {
        $counts = DB::table('record_flags')
            ->where('status', 'approved')
            ->groupBy('flagged_by_user_id')
            ->select('flagged_by_user_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'record_flags', $counts);
    }

    private function addModelsUploaded(Collection &$scores): void
    {
        $counts = DB::table('models')
            ->where('approval_status', 'approved')
            ->where('created_at', '>=', config('community-scores.demos_uploaded_after', '2026-03-14'))
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'models_uploaded', $counts);
    }

    private function addRenderRequests(Collection &$scores): void
    {
        $counts = DB::table('rendered_videos')
            ->where('source', 'web')
            ->where('status', 'completed')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'render_requests', $counts);
    }

    private function addClanCreated(Collection &$scores): void
    {
        $counts = DB::table('clans')
            ->groupBy('admin_id')
            ->select('admin_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'clan_created', $counts);
    }

    private function addClanMembership(Collection &$scores): void
    {
        $memberIds = DB::table('clan_players')
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->unique();

        foreach ($memberIds as $userId) {
            $this->ensureUser($scores, $userId);
            $data = $scores->get($userId);
            $data['clan_membership'] = true;
            $scores->put($userId, $data);
        }
    }

    private function addNsfwFlags(Collection &$scores): void
    {
        // Count NSFW-flagged maps per user
        $mapCounts = DB::table('maps')
            ->where('is_nsfw', true)
            ->whereNotNull('nsfw_flagged_by_user_id')
            ->groupBy('nsfw_flagged_by_user_id')
            ->select('nsfw_flagged_by_user_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'nsfw_flags', $mapCounts);

        // Add NSFW-flagged models per user
        $modelCounts = DB::table('models')
            ->where('is_nsfw', true)
            ->whereNotNull('nsfw_flagged_by_user_id')
            ->groupBy('nsfw_flagged_by_user_id')
            ->select('nsfw_flagged_by_user_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        foreach ($modelCounts as $row) {
            if ($scores->has($row->user_id)) {
                $data = $scores->get($row->user_id);
                $data['nsfw_flags'] += $row->cnt;
                $scores->put($row->user_id, $data);
            }
        }
    }

    private function addWikiEdits(Collection &$scores): void
    {
        $counts = DB::table('wiki_revisions')
            ->whereNull('deleted_at')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'wiki_edits', $counts);
    }

    private function addCommunityTasksCompleted(Collection &$scores): void
    {
        $counts = DB::table('community_task_votes')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'community_tasks_completed', $counts);
    }

    private function addDifficultyRatings(Collection &$scores): void
    {
        $counts = DB::table('map_difficulty_ratings')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'difficulty_ratings', $counts);
    }

    private function addRecordsCount(Collection &$scores): void
    {
        // Records link to users via mdd_id, not user_id
        $counts = DB::table('records')
            ->join('users', 'users.mdd_id', '=', 'records.mdd_id')
            ->whereNotNull('records.mdd_id')
            ->whereNotNull('users.mdd_id')
            ->groupBy('users.id')
            ->select('users.id as user_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $this->mergeGroupedCounts($scores, 'records_count', $counts);
    }

    private function addMapsAuthored(Collection &$scores): void
    {
        $claims = MapperClaim::where('type', 'map')->get();

        foreach ($claims as $claim) {
            $count = $claim->getMatchingMapsQuery()->count();
            if ($count > 0) {
                $this->ensureUser($scores, $claim->user_id);
                $data = $scores->get($claim->user_id);
                $data['maps_authored'] += $count;
                $scores->put($claim->user_id, $data);
            }
        }
    }

    private function addModelsAuthored(Collection &$scores): void
    {
        $claims = MapperClaim::where('type', 'model')->get();

        foreach ($claims as $claim) {
            // For models, match against the models table author field
            $count = DB::table('models')
                ->where('approval_status', 'approved')
                ->where('author', 'REGEXP', MapperClaim::authorRegexp($claim->name))
                ->count();

            if ($count > 0) {
                $this->ensureUser($scores, $claim->user_id);
                $data = $scores->get($claim->user_id);
                $data['models_authored'] += $count;
                $scores->put($claim->user_id, $data);
            }
        }
    }

    private function addProfileData(Collection &$scores): void
    {
        // Bulk query for all profile-related fields
        $users = DB::table('users')
            ->select(
                'id',
                'profile_photo_path',
                'profile_background_path',
                'profile_layout',
                'name_effect',
                'avatar_effect',
                'discord_id',
                'twitch_id',
                'twitter_id',
                'steam_id'
            )
            ->where(function ($q) {
                $q->whereNotNull('profile_photo_path')
                    ->orWhereNotNull('profile_background_path')
                    ->orWhereNotNull('profile_layout')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('name_effect')->where('name_effect', '!=', 'none');
                    })
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('avatar_effect')->where('avatar_effect', '!=', 'none');
                    })
                    ->orWhereNotNull('discord_id')
                    ->orWhereNotNull('twitch_id')
                    ->orWhereNotNull('twitter_id')
                    ->orWhereNotNull('steam_id');
            })
            ->get();

        foreach ($users as $user) {
            $socialCount = ($user->discord_id ? 1 : 0)
                + ($user->twitch_id ? 1 : 0)
                + ($user->twitter_id ? 1 : 0)
                + ($user->steam_id ? 1 : 0);

            $hasAvatar = !empty($user->profile_photo_path);
            $hasBackground = !empty($user->profile_background_path);
            $hasLayout = !empty($user->profile_layout);
            $hasNameEffect = !empty($user->name_effect) && $user->name_effect !== 'none';
            $hasAvatarEffect = !empty($user->avatar_effect) && $user->avatar_effect !== 'none';

            if ($socialCount > 0 || $hasAvatar || $hasBackground || $hasLayout || $hasNameEffect || $hasAvatarEffect) {
                $this->ensureUser($scores, $user->id);
                $data = $scores->get($user->id);
                $data['social_connections'] = $socialCount;
                $data['profile_avatar'] = $hasAvatar;
                $data['profile_background'] = $hasBackground;
                $data['profile_layout_customized'] = $hasLayout;
                $data['name_effect_set'] = $hasNameEffect;
                $data['avatar_effect_set'] = $hasAvatarEffect;
                $scores->put($user->id, $data);
            }
        }
    }

    private function addDonations(Collection &$scores): void
    {
        // Bulk donation calculation - group by user_id and sum per currency
        $donations = DB::table('site_donations')
            ->whereNotNull('user_id')
            ->where('status', 'approved')
            ->groupBy('user_id', 'currency')
            ->select('user_id', 'currency', DB::raw('SUM(amount) as total'))
            ->get()
            ->groupBy('user_id');

        $rates = \Cache::get('exchange_rates_v4', [
            'EUR' => 1, 'USD' => 1.08, 'CZK' => 25.3, 'GBP' => 0.86, 'PLN' => 4.28,
        ]);

        foreach ($donations as $userId => $currencyRows) {
            $eur = 0;
            foreach ($currencyRows as $row) {
                if ($row->currency === 'EUR') {
                    $eur += $row->total;
                } elseif (isset($rates[$row->currency]) && $rates[$row->currency] > 0) {
                    $eur += $row->total / $rates[$row->currency];
                }
            }

            if ($eur > 0) {
                $this->ensureUser($scores, $userId);
                $data = $scores->get($userId);
                $data['donation_total_eur'] = round($eur, 2);
                $scores->put($userId, $data);
            }
        }
    }

    private function computeTotalScores(Collection &$scores): void
    {
        $playLaterMax = config('community-scores.play_later_max_points', 10);

        foreach ($scores as $userId => $data) {
            $total = 0;

            foreach ($this->weights as $field => $weight) {
                $value = $data[$field] ?? 0;

                // Cap play_later_maps points
                if ($field === 'play_later_maps') {
                    $value = min($value, $playLaterMax);
                }

                // Booleans count as 0 or 1
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                }

                $total += $value * $weight;
            }

            $data['total_score'] = round($total, 2);

            // Community badge score = total minus records contribution
            $recordsContribution = ($data['records_count'] ?? 0) * ($this->weights['records_count'] ?? 0);
            $data['community_badge_score'] = round($total - $recordsContribution, 2);

            $data['calculated_at'] = now();
            $scores->put($userId, $data);
        }
    }

    private function assignRanks(Collection &$scores): void
    {
        $sorted = $scores->sortByDesc('total_score')->values();
        $rank = 1;
        foreach ($sorted as $data) {
            $data['rank'] = $rank++;
            $scores->put($data['user_id'], $data);
        }
    }

    private function persistScores(Collection $scores): void
    {
        if ($scores->isEmpty()) {
            return;
        }

        // Delete old scores for users no longer scoring
        CommunityHelperScore::whereNotIn('user_id', $scores->keys())->delete();

        // Upsert in chunks
        $chunks = $scores->values()->chunk(500);
        foreach ($chunks as $chunk) {
            CommunityHelperScore::upsert(
                $chunk->map(function ($data) {
                    return array_merge($data, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                })->toArray(),
                ['user_id'],
                array_keys($scores->first())
            );
        }
    }
}
