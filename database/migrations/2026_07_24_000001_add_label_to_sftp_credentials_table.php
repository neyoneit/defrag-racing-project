<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sftp_credentials', function (Blueprint $table) {
            // Owners can hold several credentials (one per VPS); the label
            // is how they tell them apart on /server-hosting ("USA box",
            // "Canada"). Null = provisioned before multi-credential support.
            $table->string('label', 40)->nullable()->after('application_id');
        });
    }

    public function down(): void
    {
        Schema::table('sftp_credentials', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
