<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_tag_id')->nullable()->after('category');
            $table->index('parent_tag_id');
        });

        Schema::dropIfExists('tag_links');
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('parent_tag_id');
        });

        Schema::create('tag_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->unsignedBigInteger('linked_tag_id');
            $table->timestamps();
            $table->unique(['tag_id', 'linked_tag_id']);
            $table->index('linked_tag_id');
        });
    }
};
