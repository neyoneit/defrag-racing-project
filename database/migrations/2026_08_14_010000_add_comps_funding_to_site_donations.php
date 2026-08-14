<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turning donated money into weeks of prize pool.
 *
 * A donation arriving is one fact; what it pays for is another, and the second
 * one cannot be derived from the first. 150 EUR is ten weeks at 15 or thirty
 * weeks at 5, and only the person who took the money and talked to the donor
 * knows which was meant. So the split is recorded rather than computed:
 *
 *   comps_amount     how much of this donation goes to comps at all (the rest
 *                    pays for running the site, which is what the donations
 *                    page already promises)
 *   comps_weeks      over how many weeklies it is spread
 *   comps_start_comp the first weekly it applies to
 *
 * Three columns on the donation rather than a table of their own, because
 * there is exactly one allocation per donation and a join would only add a
 * place for the two to disagree. Rows overlapping on a week stack, which is
 * what should happen when two people fund the same stretch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_donations', function (Blueprint $table) {
            $table->decimal('comps_amount', 10, 2)->default(0)->after('amount');
            $table->unsignedSmallInteger('comps_weeks')->nullable()->after('comps_amount');
            $table->unsignedInteger('comps_start_comp')->nullable()->after('comps_weeks');
        });

        // What a week pays stops being a whole number the moment somebody
        // spreads a round sum over a number of weeks that does not divide it:
        // 150 EUR over 10 weeks is 7.50 per physics. An integer column would
        // have silently made that 7, and the pool would quietly pay out less
        // than was donated.
        //
        // Raw ALTER rather than ->change(): on Laravel 10 a column change goes
        // through doctrine/dbal, which this project does not install.
        DB::statement('ALTER TABLE comp_rounds MODIFY prize_eur DECIMAL(8,2) UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('site_donations', function (Blueprint $table) {
            $table->dropColumn(['comps_amount', 'comps_weeks', 'comps_start_comp']);
        });

        DB::statement('ALTER TABLE comp_rounds MODIFY prize_eur INT UNSIGNED NULL');
    }
};
