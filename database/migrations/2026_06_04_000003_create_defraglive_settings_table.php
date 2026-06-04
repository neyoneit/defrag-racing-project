<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current DefragLive game settings (single evolving row, id=1) - the
 * web-native replacement for the bridge's current_settings.json. The extension
 * reads it (get_current_settings parity) and the bot syncs the live game state
 * into it (sync_settings parity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defraglive_settings', function (Blueprint $table) {
            $table->id();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defraglive_settings');
    }
};
