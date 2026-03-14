<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Discord OAuth
            $table->string('discord_id')->nullable()->unique()->after('discord_name');
            $table->text('discord_token')->nullable()->after('discord_id');
            $table->text('discord_refresh_token')->nullable()->after('discord_token');
            $table->timestamp('discord_token_expires_at')->nullable()->after('discord_refresh_token');

            // Twitch OAuth
            $table->string('twitch_id')->nullable()->unique()->after('discord_token_expires_at');
            $table->text('twitch_token')->nullable()->after('twitch_id');
            $table->text('twitch_refresh_token')->nullable()->after('twitch_token');
            $table->timestamp('twitch_token_expires_at')->nullable()->after('twitch_refresh_token');

            // Live streaming status cache
            $table->boolean('is_live')->default(false)->after('twitch_token_expires_at');
            $table->timestamp('live_status_checked_at')->nullable()->after('is_live');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'discord_id',
                'discord_token',
                'discord_refresh_token',
                'discord_token_expires_at',
                'twitch_id',
                'twitch_token',
                'twitch_refresh_token',
                'twitch_token_expires_at',
                'is_live',
                'live_status_checked_at',
            ]);
        });
    }
};
