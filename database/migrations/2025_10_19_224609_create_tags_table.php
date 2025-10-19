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
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Tag name (normalized to lowercase)
            $table->string('display_name'); // Display name (original case)
            $table->string('category')->nullable(); // items, functions, weapons, difficulty, movement
            $table->integer('usage_count')->default(0); // How many times this tag is used
            $table->timestamps();
        });

        // Pivot table for map tags
        Schema::create('map_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_id')->constrained('maps')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Who added this tag
            $table->timestamps();
            $table->unique(['map_id', 'tag_id']); // Prevent duplicate tags on same map
        });

        // Pivot table for maplist tags
        Schema::create('maplist_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maplist_id')->constrained('maplists')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Who added this tag
            $table->timestamps();
            $table->unique(['maplist_id', 'tag_id']); // Prevent duplicate tags on same maplist
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maplist_tag');
        Schema::dropIfExists('map_tag');
        Schema::dropIfExists('tags');
    }
};
