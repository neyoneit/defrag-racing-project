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
        Schema::create('player_ratings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('mdd_id');
            $table->integer('user_id')->nullable()->default(NULL);
            $table->string('physics');
            $table->string('mode');
            $table->integer('all_players_rank');
            $table->integer('active_players_rank')->nullable()->default(NULL);
            $table->integer('category_total_participators');
            $table->integer('player_records_in_category');
            $table->datetime('last_activity');
            $table->double('player_rating');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_ratings');
    }
};
