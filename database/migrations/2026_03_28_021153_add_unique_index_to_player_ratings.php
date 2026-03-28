<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicates first (keep lowest id per mdd_id/physics/mode/category)
        DB::statement('DELETE p1 FROM player_ratings p1
            INNER JOIN player_ratings p2
            WHERE p1.id > p2.id
            AND p1.mdd_id = p2.mdd_id
            AND p1.physics = p2.physics
            AND p1.mode = p2.mode
            AND p1.category = p2.category');

        Schema::table('player_ratings', function (Blueprint $table) {
            $table->unique(['mdd_id', 'physics', 'mode', 'category'], 'player_ratings_unique');
        });
    }

    public function down(): void
    {
        Schema::table('player_ratings', function (Blueprint $table) {
            $table->dropUnique('player_ratings_unique');
        });
    }
};
