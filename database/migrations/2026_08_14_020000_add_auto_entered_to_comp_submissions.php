<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the launcher entered this run, or a person did.
 *
 * The launcher recognises a comps map from the demo's FILENAME, which is a
 * convention rather than a guarantee - a renamed file, somebody else's demo, a
 * map whose name happens to match. So the server decides again once the demo is
 * parsed, and the two cases have to be told apart there:
 *
 * A person who uploaded the wrong file should see it rejected and know why.
 * A guess the launcher made should leave no trace: the entry is dropped and the
 * demo becomes an ordinary upload, exactly as it would have been if the
 * launcher had never routed it here.
 *
 * Without this column both look identical afterwards, and either the guess
 * silently hides somebody's ordinary demo for a week or the mistake is silently
 * published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_submissions', function (Blueprint $table) {
            $table->boolean('auto_entered')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('comp_submissions', function (Blueprint $table) {
            $table->dropColumn('auto_entered');
        });
    }
};
