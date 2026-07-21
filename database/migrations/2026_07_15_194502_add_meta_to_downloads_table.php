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
        Schema::table('downloads', function (Blueprint $table) {
            // Free-form details for auto-synced entries: version, release date,
            // remote size, filename. Those files live on someone else's host,
            // so there is no download_files row to carry them.
            $table->json('meta')->nullable()->after('source_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('downloads', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
