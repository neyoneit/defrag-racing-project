<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendered_videos', function (Blueprint $table) {
            $table->id();

            // Demo metadata
            $table->string('map_name')->index();
            $table->string('player_name');
            $table->string('physics')->nullable();
            $table->unsignedInteger('time_ms')->nullable();
            $table->string('gametype')->nullable();

            // Links to existing models
            $table->unsignedBigInteger('record_id')->nullable()->index();
            $table->unsignedBigInteger('demo_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Source and requester
            $table->enum('source', ['discord', 'web', 'auto'])->default('auto')->index();
            $table->string('requested_by')->nullable();

            // Queue and priority
            $table->enum('status', ['pending', 'rendering', 'uploading', 'completed', 'failed'])->default('pending')->index();
            $table->unsignedTinyInteger('priority')->default(3);
            $table->text('failure_reason')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);

            // Demo source
            $table->text('demo_url');
            $table->string('demo_filename')->nullable();

            // YouTube result
            $table->string('youtube_url')->nullable();
            $table->string('youtube_video_id', 20)->nullable()->unique();

            // Render metrics
            $table->unsignedInteger('render_duration_seconds')->nullable();
            $table->unsignedBigInteger('video_file_size')->nullable();

            // Admin
            $table->boolean('is_visible')->default(true);

            $table->timestamps();

            // Foreign keys
            $table->foreign('record_id')->references('id')->on('records')->nullOnDelete();
            $table->foreign('demo_id')->references('id')->on('uploaded_demos')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendered_videos');
    }
};
