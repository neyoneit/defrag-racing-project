<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('steam_id')->nullable()->unique()->after('twitch_token_expires_at');
            $table->string('steam_name')->nullable()->after('steam_id');
            $table->string('steam_avatar')->nullable()->after('steam_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['steam_id', 'steam_name', 'steam_avatar']);
        });
    }
};
