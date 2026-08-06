<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A player taking their own invalid time down, before anyone reports it.
 *
 * The row is a SNAPSHOT rather than a pointer, because the record it
 * describes is deleted in the same breath. The public log has to be able to
 * say "this time, on this map, was withdrawn" long after the record row is
 * gone, and a join to a deleted record cannot do that.
 *
 * There is no status and no reviewer. The whole point of the amnesty is that
 * admitting it costs nothing and needs nobody's approval - the moment it
 * needs a verdict, people stop using it and report each other instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_self_reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('mdd_id')->nullable()->index();
            $table->string('player_name')->nullable();

            // Kept for forensics only - the record itself is soft-deleted, so
            // nothing may be looked up through this in the public log.
            $table->unsignedBigInteger('record_id')->nullable()->index();

            $table->string('mapname')->nullable()->index();
            $table->string('physics')->nullable();
            $table->string('mode')->nullable();
            $table->string('gametype')->nullable();
            $table->unsignedBigInteger('time')->nullable();

            // One of RecordFlagController::FLAG_TYPES, so a self-report reads
            // the same as a report somebody else would have filed.
            $table->string('reason');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_self_reports');
    }
};
