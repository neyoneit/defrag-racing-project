<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialized Demos Top ranking.
 *
 * One row per ranked entry in a map's unified field (main MDD records +
 * Demos Top representatives, ranked by time exactly like the web map detail
 * with "Show Offline" on - oldtop is intentionally excluded for now). Built
 * by a job calling DemosTopService so the heavy union-find clustering runs at
 * write time, not on every page load, and so the auto render queue can filter
 * on a single precomputed flag instead of re-deriving ranks in SQL.
 *
 * `auto_render_eligible` folds all three queue rules into one bool computed at
 * rebuild time:
 *   - representative only (time-history demos never get an eligible row),
 *   - rank within the better half of the field (rank * 2 <= group_total),
 *   - at most the 3 oldest of any identical time in the group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demos_top_ranks', function (Blueprint $table) {
            $table->id();

            // Group identity. group_gametype is the full main gametype the
            // unified field is built for (e.g. "run_cpm"); physics is the
            // CPM/VQ3 side. The LIKE pattern used for Demos Top reps is stored
            // so a rebuild is reproducible and debuggable.
            $table->string('map_name');
            $table->string('group_gametype', 32);
            $table->string('physics', 16);
            $table->string('physics_pattern', 32);

            // 'main' = main MDD record row, 'dt_online' / 'dt_offline' = Demos
            // Top representative. Only rows carrying an uploaded_demo_id are
            // renderable; demo-less main records still occupy a rank slot so
            // the field size / everyone else's rank stay correct.
            $table->string('entry_type', 16);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->unsignedBigInteger('uploaded_demo_id')->nullable();

            $table->unsignedBigInteger('time_ms');
            $table->dateTime('date_set')->nullable();

            // rank is null for flagged entries (skipped from the field, same as
            // the web). group_total = count of non-null ranks in this group.
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedInteger('group_total')->default(0);
            $table->unsignedInteger('grouped_count')->default(0);

            $table->boolean('is_representative')->default(false);
            $table->boolean('auto_render_eligible')->default(false);

            $table->timestamps();

            // Queue lookup is by demo id; rebuild + reads are by group.
            $table->index('uploaded_demo_id');
            $table->index(['map_name', 'group_gametype']);
            $table->index(['map_name', 'physics']);
            $table->index('record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demos_top_ranks');
    }
};
