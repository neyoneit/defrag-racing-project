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
            // Index for faster user_id lookups
            $table->index('user_id', 'idx_records_user_id');

            // Composite index for map/physics/mode lookups (world record queries)
            $table->index(['mapname', 'physics', 'mode', 'time'], 'idx_records_map_physics_mode_time');

            // Index for date-based queries (recent activity)
            $table->index('date_set', 'idx_records_date_set');

            // Composite index for user + date queries
            $table->index(['user_id', 'date_set'], 'idx_records_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('idx_records_user_id');
            $table->dropIndex('idx_records_map_physics_mode_time');
            $table->dropIndex('idx_records_date_set');
            $table->dropIndex('idx_records_user_date');
        });
    }
};
