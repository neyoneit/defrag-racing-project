<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_donations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->after('note');
        });

        // Set all existing donations to approved
        DB::table('site_donations')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('site_donations', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
