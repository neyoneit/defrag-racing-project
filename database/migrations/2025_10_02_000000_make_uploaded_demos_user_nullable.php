<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing foreign key, alter column to nullable, re-add foreign key with SET NULL on delete
        Schema::table('uploaded_demos', function (Blueprint $table) {
            // Attempt to drop foreign key if it exists
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable $e) {
                // ignore if it doesn't exist
            }
        });

        // Modify the column to allow NULL (use raw statement for portability)
        DB::statement('ALTER TABLE uploaded_demos MODIFY COLUMN user_id BIGINT UNSIGNED NULL');

        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable $e) {
            }
        });

        // Make column NOT NULL again (if you want to revert)
        DB::statement('ALTER TABLE uploaded_demos MODIFY COLUMN user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
