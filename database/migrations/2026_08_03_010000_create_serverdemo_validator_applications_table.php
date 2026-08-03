<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applications for the serverdemo validator position.
 *
 * Step one of becoming a validator: anyone may apply. Step two is a vote
 * among the applicants themselves, which happens off-site for now - this
 * table only has to record who put their name forward and what came of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serverdemo_validator_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('motivation');
            $table->text('experience')->nullable();
            // Free text on purpose: "evenings CET", "weekends", whatever the
            // applicant wants to promise. Nothing reads it programmatically.
            $table->string('availability')->nullable();
            $table->string('contact')->nullable();

            // pending -> shortlisted (survived the vote) -> approved | rejected
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serverdemo_validator_applications');
    }
};
