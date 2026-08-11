<?php

namespace App\Services;

use App\Models\Map;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tells everyone who wants to know that a map was released - one notification
 * per map, whatever the scrape run brought in.
 *
 * A run that found several maps used to fold them into a single summary row
 * ("5 maps"), which could only point at the map list: it named at most three
 * of them and none of them was clickable. A notification that cannot say which
 * map it is about is not worth the line it takes up.
 */
class NewMapNotifier
{
    public const TYPE = 'new_map';

    /**
     * Rows per INSERT. Worldspawn publishes in bursts - 55 maps landed on one
     * day in July - and a burst is multiplied by every recipient, so the fan
     * out has to be handed to the database in bounded pieces.
     */
    private const INSERT_CHUNK = 1000;

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

        $payloads = $maps->map(fn (Map $map) => $this->payload($map))->all();
        $now = now();
        $sent = 0;

        // A plain insert rather than one Notification per user: the payloads are
        // identical for everybody, and the announcement fan-out's
        // User::all()->each is already the slowest thing an admin can trigger.
        User::where('map_news', true)
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $users) use ($payloads, $now, &$sent) {
                $rows = [];

                foreach ($users as $user) {
                    foreach ($payloads as $payload) {
                        $rows[] = $payload + [
                            'user_id' => $user->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                    DB::table('notifications')->insert($chunk);
                }

                $sent += count($rows);
            });

        return $sent;
    }

    /**
     * The notification body for one map, shared by every recipient. The
     * frontend renders `before`, then `headline` as the link, then `after`.
     */
    private function payload(Map $map): array
    {
        return [
            'read' => false,
            'type' => self::TYPE,
            'image' => $map->thumbnail,
            // The label leads and the map name is the headline, so the header
            // banner reads "New map: mapname" - it prints the headline and
            // nothing else.
            'before' => 'New map:',
            'headline' => Str::limit($map->name, 200, ''),
            'after' => $map->author ? Str::limit('by ' . $map->author, 200, '') : '',
            // Encoded, because 33 maps carry a "#" in the name and an unescaped
            // one turns the rest of the link into a fragment.
            'url' => '/maps/' . rawurlencode($map->name),
        ];
    }
}
