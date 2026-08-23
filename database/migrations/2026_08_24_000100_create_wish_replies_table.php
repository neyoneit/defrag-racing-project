<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A wish is answered by an admin, and the person who asked for it can answer
 * back. Deliberately not comments: anybody else who wants something files
 * their own wish, so the board stays a list of asks rather than a forum, and
 * the thread stays a conversation between the two people it concerns.
 *
 * Append-only. A reply cannot be edited or taken back, which is what keeps the
 * author from quietly turning an answered wish into a different request after
 * people have voted on the first one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wish_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wish_id')->constrained()->cascadeOnDelete();
            // Kept when the account goes: the thread reads as a conversation
            // and a hole in it reads as a missing answer. The page falls back
            // to "deleted account", the way the wish itself already does.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Stamped at write time rather than read off the user now. Somebody
            // who answers as an admin and stops being one later still answered
            // as one, and relabelling old replies would rewrite history.
            $table->boolean('by_admin')->default(false);
            $table->text('body');
            $table->timestamps();

            $table->index(['wish_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wish_replies');
    }
};
