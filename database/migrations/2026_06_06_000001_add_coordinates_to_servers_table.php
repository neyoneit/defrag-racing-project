<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geo coordinates for each server, set once in the admin. Combined with the
 * visitor's location (from Cloudflare's cf-iplatitude/cf-iplongitude request
 * headers) we estimate the visitor's ping to each server at render time -
 * great-circle distance -> rough RTT, no probes, no per-visitor storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
