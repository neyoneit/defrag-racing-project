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
        Schema::create('site_donations', function (Blueprint $table) {
            $table->id();
            $table->string('donor_name')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3); // EUR, USD, CZK
            $table->date('donation_date');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_donations');
    }
};
