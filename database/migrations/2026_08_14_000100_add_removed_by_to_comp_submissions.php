<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tells a run an admin took out from a run the validator never accepted.
 *
 * Both end up as `invalid`, but they are not the same event. A demo of the
 * wrong map is somebody picking the wrong file, and putting that on the round
 * page for everyone to read would be publishing their slip. A run an admin
 * removed was a real entry that stood in the standings, and hiding the fact
 * that it was pulled would make the entrant list quietly wrong.
 *
 * So only the second kind stays visible, and this is what separates them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_submissions', function (Blueprint $table) {
            $table->foreignId('removed_by')->nullable()->after('invalid_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable()->after('removed_by');
        });
    }

    public function down(): void
    {
        Schema::table('comp_submissions', function (Blueprint $table) {
            $table->dropForeign(['removed_by']);
            $table->dropColumn(['removed_by', 'removed_at']);
        });
    }
};
