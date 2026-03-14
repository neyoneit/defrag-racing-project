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
        Schema::table('headhunter_challenges', function (Blueprint $table) {
            $table->string('reward_currency', 5)->nullable()->default('USD')->change();
        });
    }

    public function down(): void
    {
        Schema::table('headhunter_challenges', function (Blueprint $table) {
            $table->string('reward_currency', 3)->nullable()->default('USD')->change();
        });
    }
};
