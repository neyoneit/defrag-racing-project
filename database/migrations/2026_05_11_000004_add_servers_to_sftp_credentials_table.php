<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sftp_credentials', function (Blueprint $table) {
            // Working set of the user's declared servers — copied from
            // server_owner_applications.server_info on approval, then
            // mutable by admin (rs_code is filled in here). Shape:
            //   [{gametype, ip, port, rcon, rs_code}]
            // Kept on the credential, not the application, because the
            // application stays as an immutable historical record.
            $table->json('servers')->nullable()->after('remote_path');
        });
    }

    public function down(): void
    {
        Schema::table('sftp_credentials', function (Blueprint $table) {
            $table->dropColumn('servers');
        });
    }
};
