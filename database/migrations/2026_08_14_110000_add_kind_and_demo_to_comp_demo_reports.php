<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A report that is not about somebody else's entry.
 *
 * The table was built for one thing: "that run is not what it claims to be".
 * The guard created a second, and more common, thing - "my demo went in and
 * nothing happened to it". A demo the parser could not read has no entry to
 * hang a report on, and neither does a run held for a map that is being voted
 * on, so the report has to be able to point at the demo itself.
 *
 * One table rather than two, because both end in the same place: an admin
 * opening the file to see what is going on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_demo_reports', function (Blueprint $table) {
            // A help report has no entry to point at.
            $table->foreignId('comp_submission_id')->nullable()->change();

            $table->foreignId('uploaded_demo_id')
                ->nullable()
                ->after('comp_submission_id')
                ->constrained('uploaded_demos')
                ->nullOnDelete();

            $table->enum('kind', ['entry', 'help'])
                ->default('entry')
                ->after('uploaded_demo_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('comp_demo_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_demo_id');
            $table->dropColumn('kind');
        });
    }
};
