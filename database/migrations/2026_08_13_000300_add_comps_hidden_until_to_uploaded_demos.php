<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps a comps entry out of public sight until its round is over.
 *
 * A comps demo is an ordinary upload - it goes through the same parser, gets
 * matched to a record the same way, and afterwards it belongs in /demos, on
 * the map page and on the uploader's profile like any other. It just must not
 * appear there while the round is still being played, because the demo is the
 * route and publishing it mid-round hands it to everyone still to run.
 *
 * A timestamp rather than a boolean, so nothing has to remember to flip it:
 * the moment the round's end passes, the demo becomes visible on its own. The
 * scheduler clears it as well when it closes a round, in case an admin moved
 * the end time after the entry was made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->timestamp('comps_hidden_until')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropColumn('comps_hidden_until');
        });
    }
};
