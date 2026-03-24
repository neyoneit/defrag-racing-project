<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('map_nsfw_flags');
        Schema::dropIfExists('model_nsfw_flags');
    }

    public function down(): void
    {
        // Tables recreated by their original migrations if needed
    }
};
