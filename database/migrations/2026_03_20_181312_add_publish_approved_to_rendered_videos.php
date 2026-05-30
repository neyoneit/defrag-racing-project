<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::table change isn't transactional in MySQL, so a previous
        // failed run of this migration may have already added the column
        // even though the migrations table wasn't marked.
        if (!Schema::hasColumn('rendered_videos', 'publish_approved')) {
            Schema::table('rendered_videos', function (Blueprint $table) {
                // No ->after() - the sibling migration 2026_03_20_200000 that
                // adds `published_at` sorts lexicographically AFTER this one,
                // so on a fresh DB `published_at` doesn't exist yet. Column
                // position in MySQL is purely cosmetic.
                $table->boolean('publish_approved')->default(false);
            });
        }

        // Mark already-published videos as approved. Skip on a fresh DB where
        // `published_at` hasn't been added yet (sibling migration 2026_03_20_200000
        // runs after this one due to lex order) - nothing to backfill anyway.
        if (Schema::hasColumn('rendered_videos', 'published_at')) {
            DB::table('rendered_videos')
                ->whereNotNull('published_at')
                ->update(['publish_approved' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rendered_videos', function (Blueprint $table) {
            $table->dropColumn('publish_approved');
        });
    }
};
