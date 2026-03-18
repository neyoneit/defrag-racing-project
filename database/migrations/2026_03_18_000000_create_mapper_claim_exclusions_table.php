<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapper_claim_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapper_claim_id')->constrained('mapper_claims')->cascadeOnDelete();
            $table->unsignedBigInteger('map_id');
            $table->timestamps();

            $table->unique(['mapper_claim_id', 'map_id']);
            $table->index('map_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapper_claim_exclusions');
    }
};
