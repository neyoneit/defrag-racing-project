<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->foreignId('nsfw_flagged_by_user_id')->nullable()->after('is_nsfw')->constrained('users')->nullOnDelete();
        });

        Schema::table('models', function (Blueprint $table) {
            $table->foreignId('nsfw_flagged_by_user_id')->nullable()->after('is_nsfw')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nsfw_flagged_by_user_id');
        });

        Schema::table('models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nsfw_flagged_by_user_id');
        });
    }
};
