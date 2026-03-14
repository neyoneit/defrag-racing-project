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
        Schema::table('record_notifications', function (Blueprint $table) {
            $table->boolean('worldrecord')->default(false)->after('read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('record_notifications', function (Blueprint $table) {
            $table->dropColumn('worldrecord');
        });
    }
};
