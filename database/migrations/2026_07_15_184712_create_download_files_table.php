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
        Schema::create('download_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('download_id')->constrained('downloads')->cascadeOnDelete();

            // 'file' is a downloadable asset, 'screenshot' is gallery imagery.
            $table->string('kind')->default('file');

            // Which filesystem disk `path` lives on. Community uploads land on
            // 'community'; the locked categories point at 'dl_storage' files.
            $table->string('disk')->default('community');
            $table->string('path');

            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('extension', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['download_id', 'kind', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_files');
    }
};
