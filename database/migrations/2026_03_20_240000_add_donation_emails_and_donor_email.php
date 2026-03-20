<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('donation_emails')->nullable()->after('pinned_models');
        });

        Schema::table('site_donations', function (Blueprint $table) {
            $table->string('donor_email')->nullable()->after('donor_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('donation_emails');
        });

        Schema::table('site_donations', function (Blueprint $table) {
            $table->dropColumn('donor_email');
        });
    }
};
