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
        Schema::table('player_map_scores', function (Blueprint $table) {
            $table->double('multiplier')->default(1.0)->after('map_score');
        });
    }

    public function down(): void
    {
        Schema::table('player_map_scores', function (Blueprint $table) {
            $table->dropColumn('multiplier');
        });
    }
};
