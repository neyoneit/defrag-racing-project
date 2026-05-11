<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sftp_credentials', function (Blueprint $table) {
            // Holds the one-time plaintext password after provisioning or
            // reset, encrypted at rest. The user's first visit to
            // /server-hosting reveals it; clicking "I've copied it" wipes
            // it. After that, only admin can rotate.
            $table->text('password_pending')->nullable()->after('remote_path');
        });
    }

    public function down(): void
    {
        Schema::table('sftp_credentials', function (Blueprint $table) {
            $table->dropColumn('password_pending');
        });
    }
};
