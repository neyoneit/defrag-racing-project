<?php

namespace App\Services;

use App\Models\Map;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tells everyone who wants to know that a map was released.
 *
 * Worldspawn publishes in bursts - 55 maps landed on one day in July - and the
 * scraper picks a whole burst up in a single run, so a batch becomes ONE
 * notification listing it instead of one per map. That is the difference
 * between roughly one row per user every other day and someone coming back
 * from a holiday to two hundred unread lines.
 */
class NewMapNotifier
{
    public const TYPE = 'new_map';

    /** How many map names the summary spells out before it counts the rest. */
    private const NAMES_IN_SUMMARY = 3;

    /**
     * @param  Collection<int, Map>  $maps  The maps one scrape run inserted.
     * @return int  Notifications written.
     */
    public function notify(Collection $maps): int
    {
        $maps = $maps->filter(fn (Map $map) => (bool) $map->visible)->values();

        if ($maps->isEmpty()) {
            return 0;
        }

        $payload = $this->payload($maps);
        $now = now();
        $sent = 0;

        // A plain insert rather than one Notification per user: the payload is
        // identical for everybody, and the announcement fan-out's
        // User::all()->each is already the slowest thing an admin can trigger.
        User::where('map_news', true)
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $users) use ($payload, $now, &$sent) {
                $rows = $users->map(fn (User $user) => $payload + [
                    'user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('notifications')->insert($rows);

                $sent += count($rows);
            });

        return $sent;
    }

    /**
     * The notification body, shared by every recipient. The frontend renders
     * `before`, then `headline` as the link, then `after`.
     */
    private function payload(Collection $maps): array
    {
        $common = [
            'read' => false,
            'type' => self::TYPE,
            'image' => $maps->first()->thumbnail,
        ];

        if ($maps->count() === 1) {
            $map = $maps->first();

            return $common + [
                // The label leads and the map name is the headline, so the
                // header banner reads "New map: mapname" - it prints the
                // headline and nothing else.
                'before' => 'New map:',
                'headline' => Str::limit($map->name, 200, ''),
                'after' => $map->author ? Str::limit('by ' . $map->author, 200, '') : '',
                'url' => '/maps/' . $map->name,
            ];
        }

        $names = $maps->take(self::NAMES_IN_SUMMARY)->pluck('name')->implode(', ');
        $rest = $maps->count() - self::NAMES_IN_SUMMARY;

        return $common + [
            'before' => 'New maps:',
            'headline' => $maps->count() . ' maps',
            'after' => Str::limit($rest > 0 ? $names . ' and ' . $rest . ' more' : $names, 200, ''),
            // /maps is ordered by date_added DESC, so the front page of it is
            // exactly the batch that was just announced.
            'url' => '/maps',
        ];
    }
}
