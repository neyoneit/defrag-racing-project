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
        Schema::table('clans', function (Blueprint $table) {
            $table->dropForeign(['featured_player_id']);
            $table->dropColumn('featured_player_id');
            $table->string('featured_stat')->nullable()->after('avatar_effect_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn('featured_stat');
            $table->unsignedBigInteger('featured_player_id')->nullable()->after('avatar_effect_color');
            $table->foreign('featured_player_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
