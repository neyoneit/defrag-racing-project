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
        Schema::create('challenge_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('headhunter_challenges')->onDelete('cascade');
            $table->foreignId('claimer_id')->constrained('users')->onDelete('cascade')->comment('User who completed the challenge');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade')->comment('Challenge creator');
            $table->text('reason')->comment('Why the dispute was filed');
            $table->text('evidence')->nullable()->comment('Evidence URLs, screenshots, etc.');
            $table->text('creator_response')->nullable();
            $table->timestamp('creator_responded_at')->nullable();
            $table->enum('status', ['pending', 'resolved_paid', 'resolved_unpaid', 'auto_banned'])->default('pending');
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['challenge_id', 'status']);
            $table->index(['creator_id', 'creator_responded_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_disputes');
    }
};
