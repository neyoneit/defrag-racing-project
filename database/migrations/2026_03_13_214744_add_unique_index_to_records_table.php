<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Remove duplicate records (keep the one with lowest id)
        DB::statement('
            DELETE r FROM records r
            INNER JOIN (
                SELECT MIN(id) as keep_id, physics, mode, mdd_id, mapname, time
                FROM records
                GROUP BY physics, mode, mdd_id, mapname, time
                HAVING COUNT(*) > 1
            ) dupes ON r.physics = dupes.physics
                AND r.mode = dupes.mode
                AND r.mdd_id = dupes.mdd_id
                AND r.mapname = dupes.mapname
                AND r.time = dupes.time
                AND r.id != dupes.keep_id
        ');

        // Step 2: Add unique index to prevent future duplicates
        Schema::table('records', function (Blueprint $table) {
            $table->unique(['physics', 'mode', 'mdd_id', 'mapname', 'time'], 'records_unique_player_map_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropUnique('records_unique_player_map_time');
        });
    }
};
