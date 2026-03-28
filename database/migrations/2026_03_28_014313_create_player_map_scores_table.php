<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_map_scores', function (Blueprint $table) {
            $table->id();
            $table->integer('mdd_id')->index();
            $table->integer('user_id')->nullable();
            $table->string('mapname', 255);
            $table->string('physics', 10);
            $table->string('mode', 10);
            $table->integer('time');
            $table->double('reltime');
            $table->double('map_score');
            $table->boolean('is_outlier')->default(false);
            $table->timestamps();

            $table->unique(['mdd_id', 'mapname', 'physics', 'mode']);
            $table->index(['mapname', 'physics', 'mode']);
            $table->index(['physics', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_map_scores');
    }
};
