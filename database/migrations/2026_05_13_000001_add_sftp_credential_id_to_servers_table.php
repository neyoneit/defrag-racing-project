<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // Set when a Server row is owned by an approved SftpCredential.
            // NULL = legacy manually-added entry. Lets the Filament panel
            // distinguish source and lets the observer find existing rows
            // to update instead of duplicating on (ip, port) collision.
            $table->foreignId('sftp_credential_id')
                ->nullable()
                ->after('rconpassword')
                ->constrained('sftp_credentials')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sftp_credential_id');
        });
    }
};
