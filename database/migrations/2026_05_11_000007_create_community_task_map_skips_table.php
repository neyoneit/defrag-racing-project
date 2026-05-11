<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_task_map_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('map_id')->constrained()->cascadeOnDelete();
            // 'rating' (skip on difficulty rating) | 'tag' (skip on tagging)
            $table->string('kind', 16);
            $table->timestamp('created_at')->useCurrent();

            // Cooldown lookup ("which maps has this user skipped recently?")
            $table->index(['user_id', 'created_at']);
            // Daily cap counting
            $table->index(['user_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_task_map_skips');
    }
};
