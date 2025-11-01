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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('upload_restricted')->default(false);
            $table->boolean('assignment_restricted')->default(false);
            $table->boolean('alias_restricted')->default(false);

            $table->index(['upload_restricted', 'assignment_restricted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['upload_restricted', 'assignment_restricted']);
            $table->dropColumn(['upload_restricted', 'assignment_restricted', 'alias_restricted']);
        });
    }
};
