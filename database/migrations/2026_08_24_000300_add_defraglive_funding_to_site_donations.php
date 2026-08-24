<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The DefragLive contest prize pool, funded the way comps already is.
 *
 * The contest prizes were being paid out of pocket and written down nowhere,
 * which left the donations page telling a small lie: it counted every euro
 * towards the hosting goal, including euro that were already promised to a
 * contest winner. Comps solved this by earmarking part of a donation; this is
 * the same idea for the other prize.
 *
 * Deliberately one column and not three. Comps needs a span of weeks because
 * a weekly's prize is derived from the money that funds it. A contest carries
 * its own `prize_amount`, so there is nothing to derive here - only money in
 * against money out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_donations', function (Blueprint $table) {
            // NOT NULL with a default of zero, matching comps_amount. A
            // nullable column here would only add a second way of writing
            // "none" and a null to trip over on every sum.
            $table->decimal('defraglive_amount', 10, 2)->default(0)->after('comps_note');

            // Which contest this funding was recorded for. Set on the rows the
            // site writes itself when a contest is created, and null on a
            // donation somebody made towards the pool in general - that is the
            // whole difference between the two, and it is what stops a contest
            // being funded twice.
            $table->foreignId('defraglive_contest_id')->nullable()
                ->after('defraglive_amount')
                ->constrained('defraglive_contests')->nullOnDelete();

            $table->string('defraglive_note', 160)->nullable()->after('defraglive_contest_id');
        });
    }

    public function down(): void
    {
        Schema::table('site_donations', function (Blueprint $table) {
            $table->dropForeign(['defraglive_contest_id']);
            $table->dropColumn(['defraglive_amount', 'defraglive_contest_id', 'defraglive_note']);
        });
    }
};
