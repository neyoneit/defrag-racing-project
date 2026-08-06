<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of our things a wish is about.
 *
 * Without it the list is one pile: somebody who only cares about the launcher
 * has to read forty website wishes to find the three that are theirs, and the
 * score stops meaning anything because it is comparing a map filter against an
 * engine change.
 *
 * Stored as a key rather than a repo name, because two of the entries are not
 * repositories at all (the YouTube channel, "something else") and a repo can be
 * renamed without the wishes losing their meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->string('project')->default('web')->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->dropColumn('project');
        });
    }
};
