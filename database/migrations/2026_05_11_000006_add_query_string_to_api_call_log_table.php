<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_call_log', function (Blueprint $table) {
            // Stored separately from `route` so the route dropdown filter
            // stays useful — only the path goes in the indexable `route`
            // column, and the noisy per-request query string lives here
            // for display only.
            $table->text('query_string')->nullable()->after('route');
        });
    }

    public function down(): void
    {
        Schema::table('api_call_log', function (Blueprint $table) {
            $table->dropColumn('query_string');
        });
    }
};
