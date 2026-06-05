<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-spectate watch history for the DefragLive watch-time contest.
 *
 * The server_state table only ever holds "now" (one evolving row), so the
 * history of who the bot has spectated - the whole basis of the contest - is
 * otherwise thrown away on every ingest. This table captures it as continuous
 * spectate stretches instead of per-tick samples: one row per uninterrupted
 * period the bot stayed on a single player, with the watched seconds accrued
 * onto it (DefragliveWatchService::accrue, called from the ingest serverstate
 * branch). A row stays "open" (ended_at null) while the bot is still on that
 * player; switching player or the bot going idle/offline closes it.
 *
 * The player is resolved to a defrag account (mdd_id) / site user where
 * possible, with player_name_clean as the grouping fallback when it isn't.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defraglive_watch_sessions', function (Blueprint $table) {
            $table->id();

            // Resolved identity (best effort). Null when the watched player
            // could not be matched to a site account - we still track them by
            // name so the leaderboard is complete; payout is handled manually.
            $table->unsignedBigInteger('mdd_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // The name as broadcast (Quake colour codes kept for display) plus a
            // normalized, colour-stripped key used to group sessions of the same
            // player when there is no mdd_id.
            $table->string('player_name')->nullable();
            $table->string('player_name_clean')->nullable()->index();

            // Context, useful for the admin view and disambiguation.
            $table->string('ip', 64)->nullable();
            $table->string('mapname')->nullable();

            // Accrued watch time for this continuous stretch.
            $table->unsignedInteger('seconds')->default(0);

            $table->timestamp('started_at')->index();
            // Last ingest tick we counted toward this session. Drives both the
            // capped accrual delta and "is this the open session" lookup.
            $table->timestamp('last_seen_at');
            // Null while the bot is still spectating this player.
            $table->timestamp('ended_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defraglive_watch_sessions');
    }
};
