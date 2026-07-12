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
        Schema::create('defraglive_watch_exclusions', function (Blueprint $table) {
            $table->id();
            // Identity to exclude - same matching rules as watch sessions:
            // mdd_id when the player resolved to a defrag account, otherwise
            // the color-stripped lowercase name. Either (or both) can be set.
            $table->unsignedInteger('mdd_id')->nullable()->index();
            $table->string('name_clean')->nullable()->index();
            $table->string('player_name');
            $table->text('reason');
            // Watch time accrued BEFORE this moment does not count. Time after
            // it counts normally, so a legit player whose nick was abused by
            // the cheater can still compete from the ban moment onward.
            $table->timestamp('excluded_before');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('defraglive_watch_exclusions');
    }
};
