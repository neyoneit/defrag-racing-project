<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A withdrawal is a REQUEST now, not the act itself.
 *
 * Two things forced this. Our leaderboard is not the only place the run exists:
 * until the MDD databases are merged, hiding a time here leaves it standing on
 * q3df.org, and somebody who was told "it is gone" and then sees it there
 * concludes we lied. And a run leaving the board is worth an admin looking at
 * it, if only to catch the wrong run being sent.
 *
 * So the player says WHEN they want it handled - now, accepting that it stays
 * visible elsewhere until the merge, or at the merge, both databases at once -
 * and an admin approves the hide. `processed_at` is the moment the record was
 * actually taken off the board, which is also the answer to "why is my run
 * still there".
 *
 * Rows that predate this were withdrawn under the old immediate behaviour, so
 * they are marked as already processed rather than reappearing as a queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_self_reports', function (Blueprint $table) {
            // immediate | on_merge. Called `handling` because `mode` on this
            // table is already the game mode of the run.
            $table->string('handling')->default('on_merge')->index()->after('reason');

            $table->timestamp('processed_at')->nullable()->index()->after('note');
            $table->unsignedBigInteger('processed_by')->nullable()->after('processed_at');

            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::table('player_self_reports')
            ->whereNull('processed_at')
            ->update(['handling' => 'immediate', 'processed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('player_self_reports', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn(['handling', 'processed_at', 'processed_by']);
        });
    }
};
