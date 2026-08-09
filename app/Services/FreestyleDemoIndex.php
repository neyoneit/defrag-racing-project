<?php

namespace App\Services;

use App\Models\UploadedDemo;
use Illuminate\Support\Facades\Cache;

/**
 * Which freestyle and trick demos belong to which profile.
 *
 * A map page can afford to resolve identities for the demos on that one map. A
 * profile cannot ask the same question in reverse, because a demo carries no
 * user id: it hangs off no record, and the nick on it only becomes a person
 * through the alias resolver. So the whole untimed set is resolved once and
 * kept, and a profile then only looks itself up.
 *
 * Without this a player's freestyle demos are reachable from the map they were
 * made on and from nowhere else.
 */
class FreestyleDemoIndex
{
    public const CACHE_KEY = 'freestyle_demos_by_profile';
    public const CACHE_TTL = 3600;

    /**
     * Demo ids per profile key ("user:<id>" / "mdd:<id>"), newest first.
     *
     * @return array<string, array<int>>
     */
    public function index(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $resolver = new DemoProfileResolver();
            $byProfile = [];

            // Identity columns only. The set runs to five figures, and the
            // pages that display it load the rest for the handful they show.
            UploadedDemo::query()
                ->whereIn('status', ['assigned', 'fallback-assigned', 'processed', 'failed-validity'])
                ->where(fn ($q) => $q->whereIn('gametype', ['fs', 'mfs'])->orWhereNull('time_ms'))
                ->orderByDesc('record_date')->orderByDesc('id')
                ->select(['id', 'player_name', 'q3df_login_name', 'q3df_login_name_colored', 'assigned_user_id'])
                ->chunk(2000, function ($demos) use ($resolver, &$byProfile) {
                    foreach ($demos as $demo) {
                        // Somebody on staff having said whose it is beats
                        // matching a nick, same as on the map page.
                        $key = $demo->assigned_user_id
                            ? 'user:' . $demo->assigned_user_id
                            : $resolver->resolve($demo);

                        if ($key) {
                            $byProfile[$key][] = $demo->id;
                        }
                    }
                });

            return $byProfile;
        });
    }

    /**
     * Demo ids for one profile. Both keys are worth asking for: a registered
     * account and the q3df profile behind it can each own demos, depending on
     * which nick was on the demo when it was made.
     *
     * @param  array<string>  $profileKeys
     * @return array<int>
     */
    public function demoIdsFor(array $profileKeys): array
    {
        $index = $this->index();
        $ids = [];

        foreach ($profileKeys as $key) {
            foreach ($index[$key] ?? [] as $id) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * What the profile shows: the maps this player has freestyle demos on,
     * most recent first, with the demos themselves for the newest few.
     *
     * @param  array<string>  $profileKeys
     */
    public function forProfile(array $profileKeys, int $mapLimit = 12): array
    {
        $ids = $this->demoIdsFor($profileKeys);

        if (! $ids) {
            return ['total' => 0, 'maps' => []];
        }

        $rows = UploadedDemo::whereIn('id', $ids)
            ->orderByDesc('record_date')->orderByDesc('id')
            ->get(['id', 'map_name', 'physics', 'gametype', 'record_date',
                   'original_filename', 'processed_filename', 'player_name']);

        $maps = [];

        foreach ($rows as $demo) {
            $map = $demo->map_name ?: '?';

            if (! isset($maps[$map])) {
                $maps[$map] = [
                    'map_name' => $map,
                    'count' => 0,
                    'physics' => [],
                    'latest' => $demo->record_date,
                    'demos' => [],
                ];
            }

            $maps[$map]['count']++;

            // VQ3 or CPM, off the front of "CPM.2" and the like.
            $physics = strtoupper(explode('.', (string) $demo->physics)[0]);

            if ($physics && ! in_array($physics, $maps[$map]['physics'], true)) {
                $maps[$map]['physics'][] = $physics;
            }

            if (count($maps[$map]['demos']) < 4) {
                $maps[$map]['demos'][] = [
                    'id' => $demo->id,
                    'label' => VideoMetadataService::demoLabel(
                        $demo->original_filename ?: $demo->processed_filename,
                        $demo->map_name
                    ),
                    'record_date' => $demo->record_date,
                    'physics' => $physics,
                ];
            }
        }

        return [
            'total' => $rows->count(),
            'maps' => array_slice(array_values($maps), 0, $mapLimit),
            'map_total' => count($maps),
        ];
    }

    /**
     * Bumped whenever the index is dropped, so the per-profile copies built on
     * top of it fall away with it instead of staying an hour behind. Staff
     * putting a name on a demo has to show on the profile straight away.
     */
    public static function generation(): int
    {
        return (int) Cache::get('freestyle_gen', 0);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::increment('freestyle_gen');
    }
}
