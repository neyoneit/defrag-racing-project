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
        // Track overall scraper progress
        Schema::create('scraper_progress', function (Blueprint $table) {
            $table->id();
            $table->integer('current_page')->default(1);
            $table->integer('detected_last_page')->nullable();
            $table->integer('records_scraped')->default(0);
            $table->timestamp('last_scrape_at')->nullable();
            $table->enum('status', ['idle', 'running', 'paused', 'completed', 'error'])->default('idle');
            $table->text('error_message')->nullable();
            $table->text('stop_reason')->nullable(); // Why did it stop?
            $table->timestamps();
        });

        // Track individual pages scraped
        Schema::create('scraped_pages', function (Blueprint $table) {
            $table->id();
            $table->integer('page_number')->unique();
            $table->integer('records_count');
            $table->string('page_fingerprint', 32); // MD5 hash of page content
            $table->enum('status', ['scraped', 'queued', 'processed'])->default('scraped');
            $table->timestamp('scraped_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'page_number']);
        });

        // Queue of individual records to process
        Schema::create('scraped_records_queue', function (Blueprint $table) {
            $table->id();
            $table->integer('page_number');
            $table->integer('record_index'); // Position within page
            $table->json('record_data');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->unique(['page_number', 'record_index']); // Prevents duplicates
            $table->index(['status', 'id']); // Fast queue processing
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraped_records_queue');
        Schema::dropIfExists('scraped_pages');
        Schema::dropIfExists('scraper_progress');
    }
};
