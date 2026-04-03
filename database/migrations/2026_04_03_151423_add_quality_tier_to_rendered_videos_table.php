<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendered_videos', function (Blueprint $table) {
            $table->tinyInteger('quality_tier')->nullable()->after('priority')->index();
            // Tiers:
            // 1 = Online WR (rank 1)
            // 2 = Offline faster/equal to WR
            // 3 = Online Top 2-10
            // 4 = Offline within 10% of WR
            // 5 = Online Rank 11+
            // 6 = Offline within 50% of WR
            // 7 = Longer demos (10-50min)
        });
    }

    public function down(): void
    {
        Schema::table('rendered_videos', function (Blueprint $table) {
            $table->dropColumn('quality_tier');
        });
    }
};
