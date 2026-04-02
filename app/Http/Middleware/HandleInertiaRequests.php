<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

use App\Models\RecordNotification;
use App\Models\Notification;
use App\Models\Announcement;
use Illuminate\Support\Facades\Cache;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        $recordsNotifications = [];
        $systemNotifications = [];
        $aliases = [];

        if ($request->user()) {
            $user = $request->user();

            // Load user aliases
            $aliases = \App\Models\UserAlias::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'alias', 'is_approved', 'created_at']);

            // Filter record notifications based on preview_records setting
            if ($user->preview_records !== 'none') {
                $recordsQuery = RecordNotification::where('read', false)
                    ->where('user_id', $user->id);

                // If preview_records is 'wr', only show world records
                if ($user->preview_records === 'wr') {
                    $recordsQuery->where('worldrecord', true);
                }

                $recordsNotifications = $recordsQuery->orderBy('created_at', 'DESC')->get();
            }

            // Filter system notifications based on preview_system setting
            $previewSystem = $user->preview_system ?? ['announcement', 'clan', 'tournament'];

            $systemQuery = Notification::where('read', false)
                ->where('user_id', $user->id);

            // Build notification type filter based on preview_system preferences
            $allowedTypes = [];

            if (in_array('announcement', $previewSystem)) {
                $allowedTypes[] = 'announcement';
            }

            if (in_array('clan', $previewSystem)) {
                $allowedTypes = array_merge($allowedTypes, [
                    'clan_invite', 'clan_kick', 'clan_accept', 'clan_leave', 'clan_transfer', 'clan_request', 'clan_request_accept', 'clan_request_reject'
                ]);
            }

            if (in_array('tournament', $previewSystem)) {
                $allowedTypes = array_merge($allowedTypes, [
                    'tournament_start', 'round_start', 'round_end'
                ]);
            }

            if (!empty($allowedTypes)) {
                $systemQuery->whereIn('type', $allowedTypes);
            }

            $systemNotifications = $systemQuery->orderBy('created_at', 'DESC')->get();
        }

        $shared = parent::share($request);

        return array_merge($shared, [
            'recordsNotifications'      =>      $recordsNotifications,
            'systemNotifications'       =>      $systemNotifications,
            'aliases'                   =>      $aliases,
            'danger'                    =>      $request->session()->get('danger'),
            'success'                   =>      $request->session()->get('success'),
            'dangerRandom'                 =>      random_int(0, 1_000_000_000),
            'successRandom'                 =>      random_int(0, 1_000_000_000),
            'canReportDemos'            =>      $request->user() ? (\App\Models\Record::where('user_id', $request->user()->id)->count() >= 30) : false,
            'canUploadDemos'            =>      $request->user() ? $request->user()->canUploadDemos() : true,
            'isVerified'                =>      $request->user() ? $request->user()->hasVerifiedEmail() : false,
            'recordsCount'              =>      $request->user() ? \App\Models\Record::where('user_id', $request->user()->id)->count() : 0,
            'physicsOrder'              =>      $request->user()?->default_physics_order ?? 'vq3_first',
            'availableBadges'           =>      $request->user() ? $this->getAvailableBadges($request->user()) : [],
            'globalLatestAnnouncement'  =>      !$request->user() ? Cache::remember('global:latest_announcement', 300, function () {
                return Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->first(['id', 'title', 'created_at']);
            }) : null,
        ]);
    }

    private function getAvailableBadges($user): array
    {
        $badges = [];

        if ($user->admin) $badges[] = 'badge_admin';
        if ($user->is_moderator && !$user->admin) $badges[] = 'badge_moderator';
        if ($user->isDonor()) $badges[] = 'badge_donor';

        $communityScore = \App\Models\CommunityHelperScore::where('user_id', $user->id)->first();
        if ($communityScore && $communityScore->total_score > 0) $badges[] = 'badge_community';

        if ($user->getTagCount() > 0) $badges[] = 'badge_tagger';

        $demoCounts = $user->getAssignedDemoCounts();
        if (($demoCounts['offline'] + $demoCounts['online']) > 0) $badges[] = 'badge_assigner';

        if (\App\Models\PlayerRating::where('mdd_id', $user->mdd_id)->exists()) $badges[] = 'player_rank';

        if ($communityScore && $communityScore->rank) $badges[] = 'community_rank';

        // Always available
        $badges[] = 'clan';
        $badges[] = 'wr_counters';
        $badges[] = 'socials';

        return $badges;
    }
}
