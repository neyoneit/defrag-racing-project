<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sftp_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('server_owner_applications')->nullOnDelete();
            $table->string('sftp_username')->unique()->comment('Unix username on storage VPS — also chroot dir name');
            $table->string('host');
            $table->unsignedSmallInteger('port');
            $table->string('remote_path')->default('/demos');
            $table->string('status')->default('active')->comment('active | revoked');
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('provisioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sftp_credentials');
    }
};
