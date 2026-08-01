<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An index of the serverdemos the MDD recordsystem uploads from community
 * servers. Until now nothing in the database knew a single one existed - the
 * admin browser was a live SFTP directory listing, which means it cannot
 * survive the files moving off the storage VPS and cannot answer the one
 * question the feature is for: does this record have a serverdemo?
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_demos', function (Blueprint $table) {
            $table->id();

            // Where it came from. owner_dir is the directory on the storage
            // VPS; the credential is resolved from it and may be missing for
            // accounts provisioned by CLI without a web credential.
            $table->string('owner_dir')->index();
            $table->foreignId('sftp_credential_id')->nullable()
                ->constrained('sftp_credentials')->nullOnDelete();

            // 500 chars keeps the unique index inside InnoDB's 3072-byte key
            // limit on utf8mb4 while being far longer than any real demo path.
            $table->string('path', 500)->unique();
            $table->string('filename');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('recorded_at')->nullable()->index();

            // Parsed out of the path - see App\Services\ServerDemoPath.
            $table->unsignedInteger('rs_server_id')->nullable()->index();
            $table->string('map_name')->nullable();
            $table->string('physics', 10)->nullable();
            $table->unsignedInteger('time_ms')->nullable();
            $table->unsignedInteger('mdd_id')->nullable();

            // Usually NULL and that is correct: a demo is written on every
            // finished run, while `records` holds only the current best time
            // per player per map. A run the player later improved on has no
            // record row at all. Nothing may assume this is populated.
            $table->unsignedBigInteger('record_id')->nullable()->index();

            // Where the bytes are. The local copy is not forever; B2 is the
            // mirror, the NAS the third copy.
            $table->boolean('on_contabo')->default(true);
            $table->boolean('on_b2')->default(false);
            $table->boolean('on_nas')->default(false);

            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            // The lookup the report workflow lives on: given a record, is
            // there a demo of exactly that run?
            $table->index(['map_name', 'physics', 'mdd_id', 'time_ms'], 'server_demos_record_lookup');

            // "everything this player ever ran here", newest first
            $table->index(['mdd_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_demos');
    }
};
