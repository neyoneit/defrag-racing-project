<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a withdrawal request ended: hidden, or beaten.
 *
 * A player who goes back and beats the time they asked us to remove has
 * settled it themselves. MDD only ever reports personal bests, so the new run
 * replaces the old record on its own and the bad time is already off the
 * board - leaving the request in the queue would be asking an admin to hide
 * something that is not there.
 *
 * The row stays either way. After the MDD merge we get the full time history,
 * and knowing which of those old times their own owner disowned is exactly
 * what makes that history cleanable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_self_reports', function (Blueprint $table) {
            // hidden | beaten
            $table->string('resolution')->nullable()->index()->after('processed_by');
        });

        DB::table('player_self_reports')
            ->whereNotNull('processed_at')
            ->update(['resolution' => 'hidden']);
    }

    public function down(): void
    {
        Schema::table('player_self_reports', function (Blueprint $table) {
            $table->dropColumn('resolution');
        });
    }
};
