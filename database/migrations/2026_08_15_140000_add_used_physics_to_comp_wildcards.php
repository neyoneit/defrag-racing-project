<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which physics a wildcard was spent on.
 *
 * It used to be the same as the physics it was earned in, so `physics` answered
 * both questions and nobody needed a second column. A wildcard can now be spent
 * in either physics - winning five CPM weeks and wanting to name the VQ3 map is
 * a perfectly reasonable thing to want - so the two questions have come apart:
 * `physics` is where it came from, `used_physics` is what it decided.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_wildcards', function (Blueprint $table) {
            $table->string('used_physics', 8)->nullable()->after('used_at');
        });

        // Everything spent so far was spent in the physics it was earned in,
        // because that was the only thing the code allowed.
        DB::table('comp_wildcards')
            ->whereNotNull('used_at')
            ->update(['used_physics' => DB::raw('physics')]);
    }

    public function down(): void
    {
        Schema::table('comp_wildcards', function (Blueprint $table) {
            $table->dropColumn('used_physics');
        });
    }
};
