<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `render` into every existing user's preview_system array so
 * render-related notifications start flowing into the header bell
 * preview without each user having to opt in manually through Settings.
 *
 * Also marks any render_completed notifications that already exist at
 * migration time as read=1 — when the feature deploys we don't want
 * a wall of unread notifications for renders that landed before the
 * user even knew this feature existed. Only renders completing AFTER
 * the migration should show up as unread.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Walk every user with a non-null preview_system and add
        //    'render' if it isn't already there. Users whose column is
        //    NULL fall through to the controller-side default which
        //    already includes 'render', so we don't need to touch them.
        DB::table('users')
            ->whereNotNull('preview_system')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $decoded = json_decode($user->preview_system, true);
                    if (!is_array($decoded)) {
                        continue;
                    }
                    if (in_array('render', $decoded, true)) {
                        continue;
                    }
                    $decoded[] = 'render';
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['preview_system' => json_encode(array_values($decoded))]);
                }
            });

        // 2) Anything sitting in notifications today with type
        //    render_completed is by definition a pre-feature artifact
        //    (e.g. the dev test row). Mark as read so it doesn't ping.
        DB::table('notifications')
            ->where('type', 'render_completed')
            ->update(['read' => 1]);
    }

    public function down(): void
    {
        // Strip 'render' back out — used if we ever roll the feature
        // back and want preview_system arrays to look untouched.
        DB::table('users')
            ->whereNotNull('preview_system')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $decoded = json_decode($user->preview_system, true);
                    if (!is_array($decoded)) {
                        continue;
                    }
                    if (!in_array('render', $decoded, true)) {
                        continue;
                    }
                    $decoded = array_values(array_filter($decoded, fn ($v) => $v !== 'render'));
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['preview_system' => json_encode($decoded)]);
                }
            });
    }
};
