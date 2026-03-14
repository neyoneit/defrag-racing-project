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
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['player', 'weapon', 'shadow'])->default('player');
            $table->string('author')->nullable();
            $table->string('author_email')->nullable();
            $table->string('file_path'); // Path to extracted model files
            $table->string('zip_path'); // Original ZIP file path
            $table->string('thumbnail')->nullable(); // Preview image
            $table->integer('downloads')->default(0);
            $table->integer('poly_count')->nullable();
            $table->integer('vert_count')->nullable();
            $table->boolean('has_sounds')->default(false);
            $table->boolean('has_ctf_skins')->default(false);
            $table->boolean('approved')->default(false); // For moderation
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('models');
    }
};
