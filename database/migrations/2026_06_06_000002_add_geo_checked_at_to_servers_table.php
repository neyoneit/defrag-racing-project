<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the last time we tried to geolocate a server's IP. Lets the hourly
 * geolocate command retry servers it couldn't resolve only occasionally
 * (instead of hammering the geo API every hour forever) while still picking up
 * brand-new servers promptly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('geo_checked_at')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('geo_checked_at');
        });
    }
};
