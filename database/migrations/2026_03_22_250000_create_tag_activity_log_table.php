<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('action'); // added, removed, merged
            $table->string('taggable_type'); // App\Models\Map, App\Models\Maplist
            $table->unsignedBigInteger('taggable_id');
            $table->json('metadata')->nullable(); // extra info (e.g. merge source tag)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['taggable_type', 'taggable_id']);
            $table->index('tag_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_activity_log');
    }
};
