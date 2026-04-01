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
        Schema::create('community_task_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('demo_id')->constrained('uploaded_demos')->cascadeOnDelete();
            $table->string('vote_type', 20); // assign, not_sure, no_match, correct, unassign, better_match
            $table->string('task_type', 20); // assignment, verification
            $table->unsignedBigInteger('selected_record_id')->nullable();
            $table->string('consensus_status', 20)->nullable(); // null=pending, needs_review, resolved, archived
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['demo_id', 'vote_type']);
            $table->index(['demo_id', 'user_id']);
            $table->index('consensus_status');
            $table->foreign('selected_record_id')->references('id')->on('records')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_task_votes');
    }
};
