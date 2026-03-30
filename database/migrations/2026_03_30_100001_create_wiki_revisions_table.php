<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wiki_page_id');
            $table->string('title');
            $table->longText('content');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('summary')->nullable();
            $table->timestamps();

            $table->foreign('wiki_page_id')->references('id')->on('wiki_pages')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('wiki_page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_revisions');
    }
};
