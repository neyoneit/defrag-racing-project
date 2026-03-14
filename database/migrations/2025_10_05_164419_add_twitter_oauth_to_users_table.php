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
            $table->string('twitter_id')->nullable()->unique()->after('steam_avatar');
            $table->text('twitter_token')->nullable()->after('twitter_id');
            $table->text('twitter_refresh_token')->nullable()->after('twitter_token');
            $table->timestamp('twitter_token_expires_at')->nullable()->after('twitter_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['twitter_id', 'twitter_token', 'twitter_refresh_token', 'twitter_token_expires_at']);
        });
    }
};
