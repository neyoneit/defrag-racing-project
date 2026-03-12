<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE uploaded_demos MODIFY COLUMN status ENUM('uploaded','processing','processed','failed','assigned','fallback-assigned','failed-validity','unsupported-version') NOT NULL DEFAULT 'uploaded'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE uploaded_demos MODIFY COLUMN status ENUM('uploaded','processing','processed','failed','assigned','fallback-assigned','failed-validity') NOT NULL DEFAULT 'uploaded'");
    }
};
