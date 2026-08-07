<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What hiding a run pulled off it, so putting it back can put that back too.
 *
 * Deleting a record detaches its uploaded demos (Record::deleting) and nothing
 * puts them back, which quietly costs the record its demo, the YouTube render
 * that hangs off that demo, and its place in the time history. Restoring is
 * offered as an undo, so it has to be one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_self_reports', function (Blueprint $table) {
            $table->json('detached')->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('player_self_reports', function (Blueprint $table) {
            $table->dropColumn('detached');
        });
    }
};
