<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The gate and the escalation ladder for serverdemo validation.
 *
 * Nothing reaches a validator on its own. A flag has to be cleared by the
 * admin first - without that, mass-reporting by one troll would push demos in
 * front of moderators automatically, which is the whole thing this prevents.
 *
 * After clearing, a flag walks up a ladder: one validator, then a second one
 * they hand it to, then all of them, then the admin. Each step is a wider
 * audience, never a narrower one, so `validation_stage` only moves forwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_flags', function (Blueprint $table) {
            // The gate. Null means no validator can see this flag at all.
            $table->timestamp('admin_cleared_at')->nullable()->after('admin_notes');
            $table->unsignedBigInteger('admin_cleared_by')->nullable()->after('admin_cleared_at');

            // none -> assigned -> second_opinion -> all_validators -> admin
            $table->string('validation_stage')->default('none')->after('admin_cleared_by');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable()->after('validation_stage');
            $table->timestamp('assigned_at')->nullable()->after('assigned_to_user_id');

            // Who has held this flag already, so passing it on cannot hand it
            // back to someone who has already said they are unsure.
            $table->json('validators_seen')->nullable()->after('assigned_at');

            // upheld | dismissed | inconclusive, set by whoever closes it
            $table->string('validation_outcome')->nullable()->after('validators_seen');
            $table->timestamp('validation_closed_at')->nullable()->after('validation_outcome');

            $table->foreign('admin_cleared_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['validation_stage', 'assigned_to_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('record_flags', function (Blueprint $table) {
            $table->dropForeign(['admin_cleared_by']);
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropIndex(['validation_stage', 'assigned_to_user_id']);
            $table->dropColumn([
                'admin_cleared_at',
                'admin_cleared_by',
                'validation_stage',
                'assigned_to_user_id',
                'assigned_at',
                'validators_seen',
                'validation_outcome',
                'validation_closed_at',
            ]);
        });
    }
};
