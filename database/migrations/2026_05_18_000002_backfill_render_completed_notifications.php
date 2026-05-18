<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill render_completed notifications for every historical
 * user-requested render that already shipped to YouTube. Inserted rows
 * are marked read=1 so users see their personal render history under
 * the Render tab without the bell counter spiking. New renders after
 * deploy still come in as unread via DemomeController@notifyRenderResult.
 *
 * Match criteria mirror the runtime hook in notifyRenderResult:
 *   - status = 'completed'
 *   - source != 'auto' (auto renders never notify)
 *   - user_id NOT NULL (anonymous Discord renders skip)
 *   - youtube_url NOT NULL (nothing to link to otherwise)
 * Plus we skip any rendered_video that already has a matching
 * notification, so re-running the migration is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rendered_videos')
            ->where('status', 'completed')
            ->where('source', '!=', 'auto')
            ->whereNotNull('user_id')
            ->whereNotNull('youtube_url')
            ->orderBy('id')
            ->chunkById(500, function ($videos) {
                $now = now();
                $rows = [];

                foreach ($videos as $video) {
                    $mapName = $video->map_name ?: 'unknown map';
                    $cleanMap = \App\Services\ContentFilter::filterText($mapName);
                    $mapUrl = $video->map_name
                        ? route('maps.map', ['mapname' => $video->map_name])
                        : null;

                    $existing = DB::table('notifications')
                        ->where('user_id', $video->user_id)
                        ->where('type', 'render_completed')
                        ->where('url', $video->youtube_url);

                    if ($existing->exists()) {
                        // Older render_completed rows (pre-subheadline) may be
                        // missing the map link — patch it in opportunistically.
                        if ($mapUrl) {
                            (clone $existing)
                                ->whereNull('subheadline')
                                ->update([
                                    'subheadline' => $mapUrl,
                                    'updated_at'  => $now,
                                ]);
                        }
                        continue;
                    }

                    $rows[] = [
                        'user_id'     => $video->user_id,
                        'type'        => 'render_completed',
                        'before'      => $cleanMap,
                        'headline'    => 'render is ready',
                        'after'       => '',
                        'subheadline' => $mapUrl,
                        'url'         => $video->youtube_url,
                        'read'        => 1,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('notifications')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // No-op: rolling back would risk deleting notifications a user
        // has already manually marked read post-deploy. If you really
        // need to undo, do it via a tagged one-off query on the box.
    }
};
