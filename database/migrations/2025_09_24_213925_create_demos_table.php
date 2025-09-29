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
        Schema::create('uploaded_demos', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename'); // Original uploaded filename
            $table->string('processed_filename')->nullable(); // Filename after BatchDemoRenamer
            $table->string('file_path'); // Storage path
            $table->integer('file_size'); // File size in bytes
            $table->unsignedBigInteger('user_id'); // Who uploaded it
            $table->unsignedBigInteger('record_id')->nullable(); // Assigned record
            $table->string('map_name')->nullable(); // Extracted from demo
            $table->string('physics')->nullable(); // CPM/VQ3
            $table->integer('time_ms')->nullable(); // Time in milliseconds
            $table->string('player_name')->nullable(); // Player name from demo
            $table->enum('status', ['uploaded', 'processing', 'processed', 'failed', 'assigned'])->default('uploaded');
            $table->text('processing_output')->nullable(); // BatchDemoRenamer output
            $table->timestamps();

            // Indexes
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('record_id')->references('id')->on('records')->nullOnDelete();
            $table->index('map_name');
            $table->index('status');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploaded_demos');
    }
};