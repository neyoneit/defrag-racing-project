<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step two: the applicants vote on each other.
 *
 * A round is explicit rather than a date range so the admin decides when
 * people can vote, and so a second round can be run later without touching
 * the first one's ballots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serverdemo_validator_vote_rounds', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('serverdemo_validator_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('serverdemo_validator_vote_rounds')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('serverdemo_validator_applications')->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // One ballot per voter per candidate per round. The database
            // enforces it so a double-clicked button cannot count twice.
            $table->unique(['round_id', 'application_id', 'voter_id'], 'validator_vote_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serverdemo_validator_votes');
        Schema::dropIfExists('serverdemo_validator_vote_rounds');
    }
};
