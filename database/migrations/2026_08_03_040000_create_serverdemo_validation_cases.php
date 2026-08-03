<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One case per reported PLAYER, not per reported run.
 *
 * Somebody who cheats does it on ten maps, and reviewing that as ten
 * unrelated rows is both ten times the work and worse work: the pattern
 * across the runs is usually the evidence. So the flags accumulate into a
 * case, and the case is what gets assigned, discussed and escalated.
 *
 * Identity is the MDD id where there is one - it comes from the game and
 * survives nickname changes. A site account and a display name are kept
 * alongside it for the cases where it is missing.
 *
 * Serverdemo cases and uploaded-demo cases are kept apart even for the same
 * player: the evidence is private in one and public in the other, and mixing
 * them into one thread would put both under the stricter rules for no reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serverdemo_validation_cases', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('subject_mdd_id')->nullable()->index();
            $table->unsignedBigInteger('subject_user_id')->nullable()->index();
            $table->string('subject_name')->nullable();

            // serverdemo | public_demo
            $table->string('kind')->default('serverdemo');

            // assigned -> second_opinion -> all_validators -> admin
            $table->string('validation_stage')->default('assigned');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->json('validators_seen')->nullable();

            $table->string('validation_outcome')->nullable();
            $table->timestamp('validation_closed_at')->nullable();
            $table->timestamps();

            $table->foreign('subject_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['kind', 'validation_stage']);
            $table->index(['validation_closed_at']);
        });

        Schema::table('record_flags', function (Blueprint $table) {
            $table->unsignedBigInteger('validation_case_id')->nullable()->after('admin_cleared_by')->index();
            $table->foreign('validation_case_id')->references('id')->on('serverdemo_validation_cases')->nullOnDelete();
        });

        // Comments belong to the case now - the whole point is that the
        // validators talk about the player, not about one run at a time.
        Schema::table('serverdemo_validation_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('validation_case_id')->nullable()->after('id')->index();
            $table->foreign('validation_case_id')->references('id')->on('serverdemo_validation_cases')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serverdemo_validation_comments', function (Blueprint $table) {
            $table->dropForeign(['validation_case_id']);
            $table->dropColumn('validation_case_id');
        });

        Schema::table('record_flags', function (Blueprint $table) {
            $table->dropForeign(['validation_case_id']);
            $table->dropColumn('validation_case_id');
        });

        Schema::dropIfExists('serverdemo_validation_cases');
    }
};
