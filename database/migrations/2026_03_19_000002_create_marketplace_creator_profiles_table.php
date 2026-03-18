<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_creator_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('is_listed')->default(true);
            $table->boolean('accepting_commissions')->default(true);
            $table->json('specialties')->nullable(); // ["map", "player_model", "weapon_model", "shadow_model"]
            $table->text('bio')->nullable();
            $table->string('rate_maps')->nullable();
            $table->string('rate_models')->nullable();
            $table->json('featured_map_ids')->nullable(); // up to 5 map IDs
            $table->json('portfolio_urls')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_creator_profiles');
    }
};
