<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `strafe` becomes `strafeonly`, to sit beside `cpmonly` and `vq3only`.
 *
 * The tag says a map is run without weapons whatever is lying in it, which is
 * a statement about the map and not a category name. Called `strafe` it read
 * like the category the classifier works out on its own, and the two are not
 * the same thing: the classifier calls a map strafe when it carries no gun you
 * can move with, while this tag overrules that for a map that does.
 *
 * The name lives in MapClassifier::STRAFE_TAG. Nothing else reads it.
 *
 * One thing this does break: a saved link filtering the map list by
 * `?tags=strafe` stops matching. There is no redirect for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only if the old one is there and the new one is not, so a database
        // seeded after this migration was written is left alone rather than
        // ending up with two tags nobody can tell apart.
        if (DB::table('tags')->where('name', 'strafeonly')->exists()) {
            return;
        }

        DB::table('tags')->where('name', 'strafe')->update([
            'name' => 'strafeonly',
            'display_name' => 'Strafe only',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('tags')->where('name', 'strafeonly')->update([
            'name' => 'strafe',
            'display_name' => 'Strafe',
            'updated_at' => now(),
        ]);
    }
};
