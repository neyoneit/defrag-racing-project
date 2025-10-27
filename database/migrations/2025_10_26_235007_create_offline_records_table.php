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
        Schema::create('offline_records', function (Blueprint $table) {
            $table->id();
            $table->string('map_name')->index(); // Map name
            $table->string('physics', 10)->index(); // VQ3 or CPM
            $table->string('gametype', 10)->index(); // df, fs, fc
            $table->integer('time_ms'); // Time in milliseconds
            $table->string('player_name')->nullable(); // Player name from demo
            $table->unsignedBigInteger('demo_id')->unique(); // FK to uploaded_demos
            $table->integer('rank')->default(1); // Pre-calculated rank
            $table->datetime('date_set'); // When the record was set
            $table->timestamps();

            // Foreign key
            $table->foreign('demo_id')->references('id')->on('uploaded_demos')->onDelete('cascade');

            // Composite indexes for fast leaderboard queries
            $table->index(['map_name', 'physics', 'gametype', 'time_ms'], 'offline_leaderboard_idx');
            $table->index(['map_name', 'physics', 'gametype', 'rank'], 'offline_rank_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_records');
    }
};
