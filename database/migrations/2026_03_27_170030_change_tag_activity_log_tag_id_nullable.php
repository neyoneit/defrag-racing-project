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
        Schema::table('tag_activity_log', function (Blueprint $table) {
            $table->dropForeign(['tag_id']);
            $table->unsignedBigInteger('tag_id')->nullable()->change();
            $table->foreign('tag_id')->references('id')->on('tags')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tag_activity_log', function (Blueprint $table) {
            $table->dropForeign(['tag_id']);
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete()->change();
        });
    }
};
