<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the demo filters.
 *
 * Measured on 369 391 rows before this ran. Each of these filters had to read
 * the whole table, because the column carried no index:
 *
 *   physics = 'CPM'                 429 ms
 *   time_ms between 10s and 20s     422 ms
 *   country = 'cz'                  453 ms
 *   order by time_ms limit 20       769 ms
 *
 * The composite is for one map at a time: it answers "the fastest demos on
 * this map in this physics" from the index alone, which is what the world
 * record comparison needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->index('physics', 'idx_demos_physics');
            $table->index('time_ms', 'idx_demos_time_ms');
            $table->index('country', 'idx_demos_country');
            $table->index('record_date', 'idx_demos_record_date');
            $table->index(['map_name', 'physics', 'time_ms'], 'idx_demos_map_physics_time');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropIndex('idx_demos_physics');
            $table->dropIndex('idx_demos_time_ms');
            $table->dropIndex('idx_demos_country');
            $table->dropIndex('idx_demos_record_date');
            $table->dropIndex('idx_demos_map_physics_time');
        });
    }
};
