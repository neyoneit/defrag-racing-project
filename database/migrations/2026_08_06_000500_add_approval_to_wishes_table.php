<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nothing appears on the wishlist until an admin lets it through.
 *
 * The list is a public page with a voting widget on it, which is the shape of
 * thing that attracts spam, abuse aimed at a person, and the same wish filed
 * five times. Deleting those afterwards means they were still on the site while
 * somebody read them, and a wish that got votes before it was removed took the
 * ordering with it.
 *
 * Separate from `status`: status is the ANSWER to a wish (considering, planned,
 * done, not happening) and only means anything once the wish is real. This is
 * whether it is a wish at all.
 *
 * Existing rows are approved on the way in - they were posted when there was no
 * gate, and retroactively hiding them would be a bug rather than a policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->index()->after('status_note');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::table('wishes')->whereNull('approved_at')->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_at', 'approved_by']);
        });
    }
};
