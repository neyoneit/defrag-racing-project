<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DefragLive watch-time contests. A contest is a time window over the watch
 * sessions; the winner is drawn by a watch-time-weighted raffle (more seconds
 * spectated = more tickets, but lower-watched players can still win) so the
 * same top grinder doesn't win every period.
 *
 * Default cadence is $5 every two weeks, but dates + prize are per-row so the
 * admin can run any window. The raffle draw stores the ticket counts and the
 * winning ticket index for transparency / reproducibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defraglive_contests', function (Blueprint $table) {
            $table->id();
            $table->string('title');

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();

            $table->decimal('prize_amount', 8, 2)->default(5.00);
            $table->string('prize_currency', 8)->default('USD');

            // draft -> active -> closed -> paid
            $table->string('status', 16)->default('draft')->index();

            // Winner (set at draw). Resolved where possible, name always kept.
            $table->unsignedBigInteger('winner_mdd_id')->nullable();
            $table->unsignedBigInteger('winner_user_id')->nullable()->index();
            $table->string('winner_name')->nullable();
            $table->unsignedInteger('winner_seconds')->nullable();

            // Raffle transparency: winner's tickets out of the total pool, and
            // the winning ticket index, so a draw can be explained / audited.
            $table->unsignedInteger('winner_tickets')->nullable();
            $table->unsignedInteger('total_tickets')->nullable();
            $table->unsignedInteger('winning_ticket')->nullable();
            $table->timestamp('drawn_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defraglive_contests');
    }
};
