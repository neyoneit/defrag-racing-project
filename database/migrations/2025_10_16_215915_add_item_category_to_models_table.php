<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'item' to the category enum
        DB::statement("ALTER TABLE models MODIFY COLUMN category ENUM('player', 'weapon', 'shadow', 'item') NOT NULL DEFAULT 'player'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'item' from the category enum
        DB::statement("ALTER TABLE models MODIFY COLUMN category ENUM('player', 'weapon', 'shadow') NOT NULL DEFAULT 'player'");
    }
};
