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
            $table->string('idle_gif')->nullable()->after('thumbnail');
            $table->string('rotate_gif')->nullable()->after('idle_gif');
            $table->string('gesture_gif')->nullable()->after('rotate_gif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn(['idle_gif', 'rotate_gif', 'gesture_gif']);
        });
    }
};
