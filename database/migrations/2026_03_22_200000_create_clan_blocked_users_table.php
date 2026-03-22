<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_blocked_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clan_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['clan_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_blocked_users');
    }
};
