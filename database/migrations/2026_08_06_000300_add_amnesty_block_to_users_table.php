<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Losing the right to withdraw your own runs.
 *
 * The amnesty is unconditional by design, which is exactly what makes it worth
 * abusing: withdraw a clean run to bury the pattern around it, or use it as a
 * laundry for whatever the next report would have found. The answer is not to
 * start reviewing withdrawals - that would kill the thing for everyone honest -
 * but to take the door away from the one person who was proven to be gaming it.
 *
 * A timestamp rather than a boolean, because "since when" is the first thing
 * anybody asks afterwards, and the reason is stored so it can be quoted back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('amnesty_blocked_at')->nullable();
            $table->string('amnesty_blocked_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['amnesty_blocked_at', 'amnesty_blocked_reason']);
        });
    }
};
