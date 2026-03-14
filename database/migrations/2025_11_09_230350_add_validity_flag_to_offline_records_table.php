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
        Schema::table('offline_records', function (Blueprint $table) {
            $table->string('validity_flag', 50)->nullable()->after('gametype');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offline_records', function (Blueprint $table) {
            $table->dropColumn('validity_flag');
        });
    }
};
