<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_map_scores', function (Blueprint $table) {
            $table->double('rank_multiplier')->default(1.0)->after('multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('player_map_scores', function (Blueprint $table) {
            $table->dropColumn('rank_multiplier');
        });
    }
};
