<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `source` was an ENUM('discord','web','auto'). Code writes many more
 * values (launcher, migration, community_tasks, demome, manual, ...),
 * and MySQL in non-strict mode silently coerces any value outside the
 * enum to '' - so launcher renders were landing with source = '' and
 * could never be backfilled. Convert to a plain VARCHAR so every source
 * the app uses is stored verbatim and new ones never get truncated.
 *
 * Done via raw SQL (not ->change()) because doctrine/dbal doesn't model
 * MySQL ENUM columns and would choke on the diff. MODIFY keeps the
 * existing index on the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rendered_videos MODIFY source VARCHAR(50) NOT NULL DEFAULT 'auto'");
    }

    public function down(): void
    {
        // Anything outside the original enum (launcher, etc.) would be
        // rejected by the enum on the way back, so normalize first.
        DB::statement("UPDATE rendered_videos SET source = 'auto' WHERE source NOT IN ('discord','web','auto')");
        DB::statement("ALTER TABLE rendered_videos MODIFY source ENUM('discord','web','auto') NOT NULL DEFAULT 'auto'");
    }
};
