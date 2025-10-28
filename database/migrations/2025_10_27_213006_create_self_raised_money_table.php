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
        Schema::create('self_raised_money', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // youtube, twitch, etc.
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3); // EUR, USD, CZK
            $table->date('earned_date');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_raised_money');
    }
};
