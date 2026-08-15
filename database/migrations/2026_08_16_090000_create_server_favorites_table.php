<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('server_id')->constrained('servers')->onDelete('cascade');
            $table->timestamps();

            // One star per person per server, and the server list asks
            // "which of these did this user star" on every page load.
            $table->unique(['user_id', 'server_id']);
            $table->index('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_favorites');
    }
};
