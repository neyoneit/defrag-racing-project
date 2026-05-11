<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill worldrecord flag on historical notifications by matching
        // each notification to its source record (same map/physics/mode/player/time)
        // and copying rank==1 over as worldrecord=true.
        // Records may have been re-ranked since the notification was created,
        // so the historical accuracy isn't perfect — this reflects current rank.
        DB::statement("
            UPDATE record_notifications rn
            INNER JOIN records r
                ON r.mapname = rn.mapname
                AND r.physics = rn.physics
                AND r.mode = rn.mode
                AND r.mdd_id = rn.mdd_id
                AND r.time = rn.time
                AND r.deleted_at IS NULL
            SET rn.worldrecord = 1
            WHERE r.rank = 1
        ");

        // Mark all existing notifications as read so the backfilled WR ones
        // don't suddenly resurface as unread; only newly-generated notifications
        // (post-deploy) should appear unread.
        DB::statement("UPDATE record_notifications SET `read` = 1");
    }

    public function down(): void
    {
        // Not reversible — we can't tell which notifications were originally
        // read vs. marked read by this migration. Leave as-is.
    }
};
