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
        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('headhunter_challenges')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['participating', 'submitted', 'approved', 'rejected'])->default('participating');
            $table->foreignId('record_id')->nullable()->constrained('records')->onDelete('set null')->comment('Reference to uploaded record as proof');
            $table->text('submission_notes')->nullable()->comment('Notes from participant about their submission');
            $table->text('rejection_reason')->nullable()->comment('Why submission was rejected');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
            $table->index(['challenge_id', 'status']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_participants');
    }
};
