<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Latest DefragLive server-state snapshot.
 *
 * The extension only ever wants "now" (current map, players, who the bot is
 * spectating), so this is a single evolving row (id = 1) the bridge upserts
 * via the ingest API. DefragliveJsonWriter dumps `payload` straight to the
 * public serverstate.json the extension feeds into updateServerstate().
 *
 * Per-tick spectate history for the giveaway leaderboard is a separate
 * sampling table added in a later phase - this one is display-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defraglive_server_state', function (Blueprint $table) {
            $table->id();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defraglive_server_state');
    }
};
