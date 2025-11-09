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
        // Add 'fallback-assigned' to the status enum
        // This status indicates an online demo that created an offline_record as fallback
        // but is still eligible for rematching to an online record
        DB::statement("ALTER TABLE uploaded_demos MODIFY COLUMN status ENUM('uploaded', 'processing', 'processed', 'failed', 'assigned', 'fallback-assigned') NOT NULL DEFAULT 'uploaded'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'fallback-assigned' from the status enum
        // Note: This will convert any 'fallback-assigned' records to 'assigned'
        DB::statement("UPDATE uploaded_demos SET status = 'assigned' WHERE status = 'fallback-assigned'");
        DB::statement("ALTER TABLE uploaded_demos MODIFY COLUMN status ENUM('uploaded', 'processing', 'processed', 'failed', 'assigned') NOT NULL DEFAULT 'uploaded'");
    }
};
