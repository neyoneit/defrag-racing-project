<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When somebody took their own run out of comps.
 *
 * Withdrawing deletes the entry, which left the demo looking exactly like one
 * the guard had never entered: held, no entry, and a notice explaining a
 * situation the person had created themselves. It read as the site having
 * decided something.
 *
 * It also has to stop the demo being entered again. The guard runs on every
 * parse outcome, and a re-parse of a withdrawn demo would quietly put it back
 * in the round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->timestamp('comps_withdrawn_at')->nullable()->after('comps_hidden_until');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropColumn('comps_withdrawn_at');
        });
    }
};
