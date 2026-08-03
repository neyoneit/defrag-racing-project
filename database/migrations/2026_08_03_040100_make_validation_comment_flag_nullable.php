<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes hang on the case now, not on one report.
 *
 * The column stays for the handful written before the move and for anything
 * that ever wants to point at a single run, but it can no longer be required
 * - a note about a player is not a note about any one of their runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serverdemo_validation_comments', function (Blueprint $table) {
            $table->dropForeign(['record_flag_id']);
        });

        Schema::table('serverdemo_validation_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('record_flag_id')->nullable()->change();
            $table->foreign('record_flag_id')->references('id')->on('record_flags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serverdemo_validation_comments', function (Blueprint $table) {
            $table->dropForeign(['record_flag_id']);
        });

        Schema::table('serverdemo_validation_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('record_flag_id')->nullable(false)->change();
            $table->foreign('record_flag_id')->references('id')->on('record_flags')->cascadeOnDelete();
        });
    }
};
