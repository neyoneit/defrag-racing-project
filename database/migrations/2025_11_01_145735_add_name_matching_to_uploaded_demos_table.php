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
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->integer('name_confidence')->nullable(); // 0-100
            $table->foreignId('suggested_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('manually_assigned')->default(false);
            $table->integer('download_count')->default(0); // Track downloads for stats

            $table->index('name_confidence');
            $table->index('download_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropIndex(['name_confidence']);
            $table->dropIndex(['download_count']);
            $table->dropColumn(['name_confidence', 'suggested_user_id', 'manually_assigned', 'download_count']);
        });
    }
};
