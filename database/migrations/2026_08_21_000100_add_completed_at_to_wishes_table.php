<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a wish was finished.
 *
 * The board never recorded it. `status` moved to `done` and nothing wrote
 * down when, so the Done tab could only be sorted by score - and a wish
 * finished this morning sat below one finished in March that had collected
 * more votes. Whoever came to see what had been built lately could not.
 *
 * Backfilled from `updated_at`, which is the best that exists for rows that
 * are already done. It is right for most of them (marking a wish done IS the
 * last thing that happens to it) and wrong for any that were edited
 * afterwards. Wrong by a few days on old rows beats having no order at all,
 * and every wish finished from here on carries the real time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status_note');
        });

        DB::table('wishes')
            ->where('status', 'done')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
