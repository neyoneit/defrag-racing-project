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
        Schema::table('records', function (Blueprint $table) {
            // Composite index for recentlyBeaten and tiedRanks queries
            $table->index(['mapname', 'gametype', 'time'], 'records_map_game_time_idx');

            // Composite index for queries filtering by mapname, gametype, and mdd_id
            $table->index(['mapname', 'gametype', 'mdd_id'], 'records_map_game_mdd_idx');

            // Composite index for mdd_id with date_set for profile latest records
            $table->index(['mdd_id', 'date_set'], 'records_mdd_date_idx');

            // Composite index for mdd_id with rank for bestRanks/worstRanks
            $table->index(['mdd_id', 'rank'], 'records_mdd_rank_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('records_map_game_time_idx');
            $table->dropIndex('records_map_game_mdd_idx');
            $table->dropIndex('records_mdd_date_idx');
            $table->dropIndex('records_mdd_rank_idx');
        });
    }
};
