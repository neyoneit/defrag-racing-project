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
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->timestamp('record_date')->nullable()->after('player_name')->comment('Date when the demo was recorded (from demo metadata)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropColumn('record_date');
        });
    }
};
