<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_visits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('ip', 45);
            $table->string('user_agent', 500);
            $table->string('path', 500);
            $table->string('method', 10)->default('GET');
            $table->unsignedInteger('hits')->default(1);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamps();

            $table->index(['date', 'hits']);
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_visits');
    }
};
