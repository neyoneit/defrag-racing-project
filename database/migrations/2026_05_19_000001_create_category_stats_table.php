<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_stats', function (Blueprint $table) {
            $table->string('physics', 8);
            $table->string('mode', 8);
            $table->string('category', 16);
            $table->double('median_players')->default(0);
            $table->unsignedInteger('ranked_maps')->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->primary(['physics', 'mode', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_stats');
    }
};
