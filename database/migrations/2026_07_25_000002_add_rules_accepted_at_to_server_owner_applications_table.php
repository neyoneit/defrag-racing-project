<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_owner_applications', function (Blueprint $table) {
            // When the applicant ticked "I agree to the server hosting
            // rules" on /server-hosting. Nullable: pre-existing
            // applications predate the rules checkbox.
            $table->timestamp('rules_accepted_at')->nullable()->after('server_info');
        });
    }

    public function down(): void
    {
        Schema::table('server_owner_applications', function (Blueprint $table) {
            $table->dropColumn('rules_accepted_at');
        });
    }
};
