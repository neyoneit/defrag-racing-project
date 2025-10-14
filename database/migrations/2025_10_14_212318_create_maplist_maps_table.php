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
        Schema::create('maplist_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maplist_id')->constrained('maplists')->onDelete('cascade');
            $table->foreignId('map_id')->constrained('maps')->onDelete('cascade');
            $table->integer('position')->default(0); // Order of maps in the maplist
            $table->timestamps();

            // Prevent duplicate map entries in the same maplist
            $table->unique(['maplist_id', 'map_id']);
            $table->index('maplist_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maplist_maps');
    }
};
