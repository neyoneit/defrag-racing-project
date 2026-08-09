<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a demo belongs to, when no record can say it.
 *
 * A freestyle demo hangs off no record, so the only thing that ever attributed
 * one was an approved alias - and 3 in 4 of them resolve to nobody, leaving the
 * row with a question mark for an avatar. This is a staff decision recorded on
 * the demo itself, kept apart from `user_id` (whoever uploaded the file, which
 * is often an admin doing a bulk import) and from `suggested_user_id` (the
 * fuzzy matcher's guess, which asserts nothing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('suggested_user_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_user_at')->nullable()->after('assigned_by_user_id');

            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('assigned_by_user_id');
            $table->dropColumn('assigned_user_at');
        });
    }
};
