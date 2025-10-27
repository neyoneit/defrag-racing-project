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
            $table->string('gametype')->nullable()->after('physics'); // df, mdf, fs, mfs, fc, mfc, etc.
            $table->index('gametype');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropIndex(['gametype']);
            $table->dropColumn('gametype');
        });
    }
};
