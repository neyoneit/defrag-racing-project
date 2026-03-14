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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('cached_wr_count')->default(0)->after('profile_photo_path');
            $table->integer('cached_top3_count')->default(0)->after('cached_wr_count');
            $table->timestamp('ranking_cached_at')->nullable()->after('cached_top3_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cached_wr_count', 'cached_top3_count', 'ranking_cached_at']);
        });
    }
};
