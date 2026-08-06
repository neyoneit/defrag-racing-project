<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wishlist: anyone with an account writes down what they want built, and
 * everyone else says whether they want it too.
 *
 * The score is stored on the wish rather than counted from the votes on every
 * page load. The list is sorted by it, and sorting on a subquery over a table
 * that grows with every click is the one thing that would make this page slow.
 * `wish_votes` stays the source of truth; the two counters are rebuilt from it
 * whenever a vote changes.
 *
 * One row per (wish, voter) with a +1/-1 value, so changing your mind updates
 * a row instead of adding one, and nobody can vote twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('title');
            $table->text('body');

            // considering | planned | done | rejected - the admin's answer.
            $table->string('status')->default('considering')->index();
            $table->text('status_note')->nullable();

            $table->integer('upvotes')->default(0);
            $table->integer('downvotes')->default(0);
            $table->integer('score')->default(0)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('wish_votes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('wish_id');
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('value'); // +1 or -1

            $table->timestamps();

            $table->foreign('wish_id')->references('id')->on('wishes')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['wish_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wish_votes');
        Schema::dropIfExists('wishes');
    }
};
