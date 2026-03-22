<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->unsignedBigInteger('linked_tag_id');
            $table->timestamps();

            $table->unique(['tag_id', 'linked_tag_id']);
            $table->index('linked_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_links');
    }
};
