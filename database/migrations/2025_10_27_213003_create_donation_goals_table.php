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
        Schema::create('donation_goals', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->decimal('yearly_goal', 10, 2)->default(1200); // 1200 EUR yearly
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->unique('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_goals');
    }
};
