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
     * What the profile shows: a row per map, split the way the rest of the
     * site splits everything, VQ3 on one side and CPM on the other. A map with
     * demos in both physics appears in both, with only its own demos.
     *
     * Only the maps on the page being looked at load their demos, so a row can
     * open without asking the server again.
     *
     * @param  array<string>  $profileKeys
     * @param  array{vq3?: int, cpm?: int}  $pages
     */
    public function forProfile(array $profileKeys, array $pages = [], string $search = '', int $perPage = 15): array
    {
        $search = trim($search);
        $empty = [
            'total' => 0,
            'search' => $search,
            'vq3' => $this->emptyBlock($perPage),
            'cpm' => $this->emptyBlock($perPage),
        ];

        $ids = $this->demoIdsFor($profileKeys);

        if (! $ids) {
            return $empty;
        }

        // A row per map and physics. At a few hundred rows this is cheap
        // enough to count, filter and page in PHP, which keeps the search off
        // the database and the paging honest about what the search left.
        $summary = UploadedDemo::whereIn('id', $ids)
            ->whereNotNull('map_name')
            ->selectRaw('map_name, physics, COUNT(*) as demo_count, MAX(COALESCE(record_date, created_at)) as latest')
            ->groupBy('map_name', 'physics')
            ->get();

        if ($summary->isEmpty()) {
            return $empty;
        }

        $grandTotal = (int) $summary->sum('demo_count');
        $buckets = ['vq3' => [], 'cpm' => []];

        foreach ($summary as $row) {
            // "CPM.2" and "VQ3.0" are the same two physics with a fastcap flag
            // on the end.
            $physics = strtolower(explode('.', (string) $row->physics)[0]);

            if (! isset($buckets[$physics])) {
                continue;
            }

            if ($search !== '' && stripos($row->map_name, $search) === false) {
                continue;
            }

            $map = $row->map_name;

            if (! isset($buckets[$physics][$map])) {
                $buckets[$physics][$map] = ['map_name' => $map, 'count' => 0, 'latest' => null];
            }

            $buckets[$physics][$map]['count'] += (int) $row->demo_count;
            $buckets[$physics][$map]['latest'] = max($buckets[$physics][$map]['latest'], $row->latest);
        }

        $blocks = [];
        $wanted = [];

        foreach ($buckets as $physics => $maps) {
            $maps = array_values($maps);
            usort($maps, fn ($a, $b) => [$b['latest'], $a['map_name']] <=> [$a['latest'], $b['map_name']]);

            $mapTotal = count($maps);
            $lastPage = max(1, (int) ceil($mapTotal / $perPage));
            $page = max(1, min((int) ($pages[$physics] ?? 1), $lastPage));
            $slice = array_slice($maps, ($page - 1) * $perPage, $perPage);

            $blocks[$physics] = [
                'maps' => $slice,
                'map_total' => $mapTotal,
                'total' => array_sum(array_column($maps, 'count')),
                'page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
            ];

            foreach ($slice as $map) {
                $wanted[$map['map_name']] = true;
            }
        }

        $demos = $wanted
            ? UploadedDemo::whereIn('id', $ids)
                ->whereIn('map_name', array_keys($wanted))
                ->with('renderedVideo')
                ->orderByDesc('record_date')->orderByDesc('id')
                ->get(['id', 'map_name', 'physics', 'gametype', 'record_date', 'created_at', 'download_count',
                       'original_filename', 'processed_filename', 'player_name'])
            : collect();

        foreach ($blocks as $physics => $block) {
            $blocks[$physics]['maps'] = array_map(function ($map) use ($demos, $physics) {
                $list = $demos
                    ->where('map_name', $map['map_name'])
                    ->filter(fn ($d) => strtolower(explode('.', (string) $d->physics)[0]) === $physics)
                    ->map(fn ($demo) => [
                        'id' => $demo->id,
                        'label' => VideoMetadataService::demoLabel(
                            $demo->original_filename ?: $demo->processed_filename,
                            $demo->map_name
                        ) ?: ($demo->processed_filename ?: $demo->original_filename),
                        'record_date' => $demo->record_date ?: $demo->created_at,
                        'download_count' => (int) $demo->download_count,
                        'video_url' => $demo->renderedVideo?->youtube_url,
                    ])
                    ->values()->all();

                $map['demos'] = $list;
                $map['downloads'] = array_sum(array_column($list, 'download_count'));
                $map['videos'] = count(array_filter(array_column($list, 'video_url')));

                return $map;
            }, $block['maps']);
        }

        return [
            'total' => $grandTotal,
            'search' => $search,
            'vq3' => $blocks['vq3'],
            'cpm' => $blocks['cpm'],
        ];
    }

    private function emptyBlock(int $perPage): array
    {
        return ['maps' => [], 'map_total' => 0, 'total' => 0, 'page' => 1, 'last_page' => 1, 'per_page' => $perPage];
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
