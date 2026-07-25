<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sftp_credentials', function (Blueprint $table) {
            // Filled hourly by serverdemos:sync-stats from the storage
            // VPS ingest tree - passive "are demos arriving" monitoring.
            $table->timestamp('last_upload_at')->nullable()->after('servers');
            $table->unsignedInteger('demo_count')->nullable()->after('last_upload_at');

            // Filled by the on-demand / post-provision account check.
            $table->timestamp('last_checked_at')->nullable()->after('demo_count');
            $table->string('check_status', 10)->nullable()->after('last_checked_at');
            $table->text('check_message')->nullable()->after('check_status');
        });
    }

    public function down(): void
    {
        Schema::table('sftp_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'last_upload_at',
                'demo_count',
                'last_checked_at',
                'check_status',
                'check_message',
            ]);
        });
    }
};
