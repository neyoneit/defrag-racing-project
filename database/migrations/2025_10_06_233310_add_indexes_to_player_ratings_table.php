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
            // Composite index for ranking queries (physics + mode + rank columns)
            $table->index(['physics', 'mode', 'active_players_rank'], 'idx_physics_mode_active_rank');
            $table->index(['physics', 'mode', 'all_players_rank'], 'idx_physics_mode_all_rank');

            // Index for filtering active players by last_activity
            $table->index(['physics', 'mode', 'last_activity'], 'idx_physics_mode_activity');

            // Index for user lookup
            $table->index('mdd_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_ratings', function (Blueprint $table) {
            $table->dropIndex('idx_physics_mode_active_rank');
            $table->dropIndex('idx_physics_mode_all_rank');
            $table->dropIndex('idx_physics_mode_activity');
            $table->dropIndex('player_ratings_mdd_id_index');
        });
    }
};
