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
        Schema::table('defraglive_contests', function (Blueprint $table) {
            // Portion of prize_amount that was forwarded in by previous
            // winners ("Resolve prize -> forwarded"). Kept separately so the
            // public page can show "base prize + carried over" transparently.
            $table->decimal('carried_over_amount', 8, 2)->default(0)->after('prize_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('defraglive_contests', function (Blueprint $table) {
            $table->dropColumn('carried_over_amount');
        });
    }
};
