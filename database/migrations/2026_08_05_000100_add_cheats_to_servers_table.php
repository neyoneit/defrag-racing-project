<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable on purpose: sv_cheats only reaches the scraper from an engine
     * that puts it in the getdfstatus reply, so null means "this server never
     * told us" and is not the same answer as cheats being off.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('cheats')->nullable()->default(null)->after('defrag_gametype');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('cheats');
        });
    }
};
