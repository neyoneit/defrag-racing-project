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
        Schema::table('player_ratings', function (Blueprint $table) {
            // Add category column: 'overall', 'rocket', 'plasma', 'grenade', 'slick', 'tele', 'bfg', 'strafe'
            $table->string('category', 50)->default('overall')->after('mode');

            // Add composite index for category-based queries
            $table->index(['physics', 'mode', 'category', 'active_players_rank'], 'idx_physics_mode_cat_active');
            $table->index(['physics', 'mode', 'category', 'all_players_rank'], 'idx_physics_mode_cat_all');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_ratings', function (Blueprint $table) {
            $table->dropIndex('idx_physics_mode_cat_active');
            $table->dropIndex('idx_physics_mode_cat_all');
            $table->dropColumn('category');
        });
    }
};
