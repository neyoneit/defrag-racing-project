<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `records.rank` on its own, for the demo rank filter.
 *
 * The table has rank inside a composite with mdd_id, which does not answer
 * "every record ranked 1 to 50". Without this the plan for a rank filter was
 * unstable: the same query measured 42 ms once and 1 031 ms the next time,
 * depending on which index the optimiser picked to walk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->index('rank', 'idx_records_rank');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('idx_records_rank');
        });
    }
};
