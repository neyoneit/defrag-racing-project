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
        Schema::table('clans', function (Blueprint $table) {
            $table->boolean('hidden')->default(false)->after('admin_id');
            $table->boolean('banned')->default(false)->after('hidden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn(['hidden', 'banned']);
        });
    }
};
