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
        Schema::create('download_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('download_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('position')->default(0);

            // Locked categories are fed by a sync command: users cannot upload
            // into them and the tree editor refuses to rename or move them.
            $table->boolean('is_locked')->default(false);

            // 'defrag_mod' | 'server_bundle'. Null for ordinary categories.
            $table->string('auto_source')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
            $table->index(['parent_id', 'position']);
            $table->index('auto_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_categories');
    }
};
