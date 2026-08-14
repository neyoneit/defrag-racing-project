<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What this particular week pays, rather than what weeks pay in general.
 *
 * The amount used to be one global setting, which meant a donation earmarked
 * for a single weekly could not be honoured: raising the figure raised it for
 * every week from then on, and lowering it afterwards rewrote what the past
 * had paid, because nothing recorded the amount a finished week actually ran
 * with.
 *
 * Stamped from the setting when the round is created, so the default still
 * comes from one place and a round that has been created keeps its number no
 * matter what happens to that default afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_rounds', function (Blueprint $table) {
            $table->unsignedInteger('prize_eur')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('comp_rounds', function (Blueprint $table) {
            $table->dropColumn('prize_eur');
        });
    }
};
