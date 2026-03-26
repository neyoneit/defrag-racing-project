<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_difficulty_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('map_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->timestamps();

            $table->foreign('map_id')->references('id')->on('maps')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['map_id', 'user_id']);
            $table->index('map_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_difficulty_ratings');
    }
};
