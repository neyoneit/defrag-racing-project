<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->index(['gametype', 'status'], 'idx_demos_gametype_status');
            $table->index(['status', 'created_at'], 'idx_demos_status_created');
            $table->index(['user_id', 'status'], 'idx_demos_user_status');
        });

        DB::statement('CREATE INDEX idx_demos_created_desc ON uploaded_demos (created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropIndex('idx_demos_gametype_status');
            $table->dropIndex('idx_demos_status_created');
            $table->dropIndex('idx_demos_user_status');
            $table->dropIndex('idx_demos_created_desc');
        });
    }
};
