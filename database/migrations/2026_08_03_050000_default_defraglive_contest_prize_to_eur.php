<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prize is paid from one person's pocket and that pocket is in euros, so
 * EUR is the sensible default rather than the USD this shipped with.
 *
 * Existing rows are deliberately left alone: they record what was actually
 * paid out, and relabelling a settled USD prize as euros would make the hall
 * of fame lie about what people received.
 *
 * Raw SQL because the project has no doctrine/dbal, so ->change() is not
 * available. Only MySQL is touched - it is what runs here and in production,
 * and the model carries the same default anyway, so any other driver still
 * gets EUR through Eloquent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('defraglive_contests')) {
            return;
        }

        DB::statement("ALTER TABLE defraglive_contests ALTER COLUMN prize_currency SET DEFAULT 'EUR'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('defraglive_contests')) {
            return;
        }

        DB::statement("ALTER TABLE defraglive_contests ALTER COLUMN prize_currency SET DEFAULT 'USD'");
    }
};
