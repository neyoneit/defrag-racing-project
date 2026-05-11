<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reset the previous over-eager backfill — earlier migration flagged
        // every notification whose *beater* is currently WR, which fires even
        // for users who never held rank 1 themselves.
        DB::statement("UPDATE record_notifications SET worldrecord = 0");

        // Correct logic: this notification was a WR-beat iff the recipient
        // held rank 1 at the moment (their `my_time` is currently a rank-2
        // record) AND the beater is currently rank 1 with that exact time.
        // Imperfect for cases where either party has improved since — we have
        // no historical rank table — but accurate for the still-current
        // state of the leaderboard.
        DB::statement("
            UPDATE record_notifications rn
            INNER JOIN users u ON u.id = rn.user_id
            INNER JOIN records beater
                ON beater.mapname = rn.mapname
                AND beater.physics = rn.physics
                AND beater.mode = rn.mode
                AND beater.mdd_id = rn.mdd_id
                AND beater.time = rn.time
                AND beater.deleted_at IS NULL
                AND beater.rank = 1
            INNER JOIN records recipient
                ON recipient.mapname = rn.mapname
                AND recipient.physics = rn.physics
                AND recipient.mode = rn.mode
                AND recipient.mdd_id = u.mdd_id
                AND recipient.time = rn.my_time
                AND recipient.deleted_at IS NULL
                AND recipient.rank = 2
            SET rn.worldrecord = 1
        ");
    }

    public function down(): void
    {
        // Not reversible without historical rank data.
    }
};
