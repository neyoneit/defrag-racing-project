<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A wish can carry one screenshot and one video.
 *
 * Half the wishes on the board are about something you can see - a layout, a
 * badge, a filter - and describing it in prose costs the author more effort
 * than a screenshot and still leaves the reader guessing what they are voting
 * on. Only the id of the video is kept, not the URL people paste, so the embed
 * is built by us and a pasted link cannot carry anything else along.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('body');
            $table->string('youtube_id', 20)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'youtube_id']);
        });
    }
};
