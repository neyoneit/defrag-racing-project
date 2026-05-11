<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_notifications', function (Blueprint $table) {
            // Powers the per-tab listing (?record_filter=beaten|worldrecords)
            // and per-user counts. Without it the WR tab does a filesort across
            // every notification the user has received.
            $table->index(
                ['user_id', 'worldrecord', 'created_at'],
                'idx_record_notifications_user_wr_created'
            );
        });
    }

    public function down(): void
    {
        Schema::table('record_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_record_notifications_user_wr_created');
        });
    }
};
