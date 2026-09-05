<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per playlist the channel is meant to have. The definitions
        // live in code (YoutubePlaylistService); this table is what the code
        // could not know: the id YouTube handed back when the playlist was
        // created, and whether the admin has queued this one for the next sync.
        Schema::create('youtube_playlists', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('youtube_playlist_id')->nullable();
            $table->boolean('sync_queued')->default(false);
            $table->unsignedInteger('planned_count')->default(0);
            $table->unsignedInteger('synced_count')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // The snapshot: what each playlist should hold, in what order, worked
        // out at the moment the admin pressed the button. Stored rather than
        // computed by the bot so the numbers on screen are the ones that run.
        Schema::create('youtube_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_playlist_id')->constrained('youtube_playlists')->cascadeOnDelete();
            $table->foreignId('rendered_video_id')->constrained('rendered_videos')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['youtube_playlist_id', 'rendered_video_id'], 'ytpi_playlist_video_unique');
            $table->index(['youtube_playlist_id', 'position'], 'ytpi_playlist_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_playlist_items');
        Schema::dropIfExists('youtube_playlists');
    }
};
