<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting an application hides it; it never destroys it.
 *
 * Rejecting and deleting are deliberately two different acts. A rejection is
 * the decision and stays on the record forever. Deleting only clears the row
 * out of the way so the person can put their name forward again later - the
 * row itself, and the fact that they were once turned down, remains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serverdemo_validator_applications', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('serverdemo_validator_applications', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
