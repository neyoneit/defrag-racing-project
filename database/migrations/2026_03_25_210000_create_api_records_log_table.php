<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_records_log', function (Blueprint $table) {
            $table->id();
            $table->integer('mdd_id');
            $table->string('mapname');
            $table->string('physics', 10);
            $table->string('mode', 10);
            $table->integer('time');
            $table->string('name');
            $table->string('date_set');
            $table->timestamp('first_seen_at')->useCurrent();

            $table->unique(['mdd_id', 'mapname', 'physics', 'mode'], 'api_log_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_records_log');
    }
};
