<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An announcement notification kept a copy of the title as plain text and no
 * way back to the announcement it came from, so the header ticker and the
 * notification centre printed English at everybody, in every language, while
 * the announcement itself had nine translations sitting right there.
 *
 * The id closes that. It also replaces the one thing the copy was matched on:
 * renaming an announcement used to rewrite its notifications by looking for
 * rows whose headline still held the old title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id')->nullable()->after('type');
            $table->index('announcement_id');
        });

        // Every existing row, matched on the copy it carries. Titles that
        // appear on more than one announcement are skipped rather than guessed
        // at: a wrong link would show the wrong announcement's translation,
        // which is worse than the English the row shows today.
        $ambiguous = DB::table('announcements')
            ->select('title')
            ->groupBy('title')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('title');

        DB::table('announcements')
            ->whereNotIn('title', $ambiguous)
            ->orderBy('id')
            ->chunkById(100, function ($announcements) {
                foreach ($announcements as $announcement) {
                    DB::table('notifications')
                        ->where('type', 'announcement')
                        ->where('headline', $announcement->title)
                        ->update(['announcement_id' => $announcement->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['announcement_id']);
            $table->dropColumn('announcement_id');
        });
    }
};
