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
        Schema::create('maplists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_play_later')->default(false); // Special flag for the default "Play Later" maplist
            $table->integer('likes_count')->default(0);
            $table->integer('favorites_count')->default(0);
            $table->timestamps();

            // Index for faster queries
            $table->index(['user_id', 'is_play_later']);
            $table->index(['is_public', 'likes_count']);
            $table->index(['is_public', 'favorites_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maplists');
    }
};
