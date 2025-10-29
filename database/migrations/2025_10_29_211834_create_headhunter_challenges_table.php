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
        Schema::create('headhunter_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('mapname');
            $table->enum('physics', ['vq3', 'cpm']);
            $table->enum('mode', ['run', 'strafe', 'freestyle', 'fastcaps', 'any']);
            $table->integer('target_time')->comment('Target time in milliseconds');
            $table->decimal('reward_amount', 10, 2)->nullable()->comment('Monetary reward amount');
            $table->string('reward_currency', 3)->nullable()->default('USD');
            $table->text('reward_description')->nullable()->comment('Non-monetary reward description');
            $table->enum('status', ['open', 'claimed', 'completed', 'disputed', 'closed'])->default('open');
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('creator_banned')->default(false)->comment('Creator banned from creating new challenges');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
            $table->index('creator_id');
            $table->index('mapname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('headhunter_challenges');
    }
};
