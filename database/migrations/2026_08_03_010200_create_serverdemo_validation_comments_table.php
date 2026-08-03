<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal notes validators leave each other on a flagged record.
 *
 * Never public and never shown to the reporter or the player being reported -
 * this is where "I am not sure, the strafe at 0:14 looks off" lives, and it
 * has to be readable by whoever the flag gets handed to next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serverdemo_validation_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_flag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            // Marks the automatic entries - handed over, escalated, closed -
            // so the thread reads as a history and not just as chat.
            $table->string('event')->nullable();
            $table->timestamps();

            $table->index(['record_flag_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serverdemo_validation_comments');
    }
};
