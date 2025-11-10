<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'failed-validity' to the status enum
        // Demos with validity issues (sv_cheats, client_finish, timescale, etc.) get this status
        DB::statement("ALTER TABLE uploaded_demos MODIFY COLUMN status ENUM('uploaded', 'processing', 'processed', 'failed', 'assigned', 'fallback-assigned', 'failed-validity') NOT NULL DEFAULT 'uploaded'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // If rolling back, convert 'failed-validity' demos back to 'processed'
        // (they were successfully processed, just had validity issues)
        DB::statement("UPDATE uploaded_demos SET status = 'processed' WHERE status = 'failed-validity'");
        DB::statement("ALTER TABLE uploaded_demos MODIFY COLUMN status ENUM('uploaded', 'processing', 'processed', 'failed', 'assigned', 'fallback-assigned') NOT NULL DEFAULT 'uploaded'");
    }
};
