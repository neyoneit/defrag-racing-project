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
        Schema::table('record_flags', function (Blueprint $table) {
            $table->json('flagged_by_users')->nullable()->after('flagged_by_user_id');
            $table->unsignedInteger('flag_count')->default(1)->after('flagged_by_users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('record_flags', function (Blueprint $table) {
            $table->dropColumn(['flagged_by_users', 'flag_count']);
        });
    }
};
