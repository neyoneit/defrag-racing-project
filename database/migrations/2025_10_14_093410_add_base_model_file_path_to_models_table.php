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
        Schema::table('models', function (Blueprint $table) {
            // Store the resolved file path for base model MD3 files
            // For complete models: same as file_path
            // For skin/mixed packs: points to the base Q3 model or found base model
            $table->string('base_model_file_path')->nullable()->after('base_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn('base_model_file_path');
        });
    }
};
