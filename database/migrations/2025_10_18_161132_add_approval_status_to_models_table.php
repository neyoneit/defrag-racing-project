<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('models', function (Blueprint $table) {
            // Add approval_status enum column (pending, approved, rejected)
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->after('approved');

            // Migrate existing approved boolean to approval_status
            // approved=true -> 'approved', approved=false -> 'pending'
        });

        // Migrate data
        DB::statement("UPDATE models SET approval_status = CASE WHEN approved = 1 THEN 'approved' ELSE 'pending' END");

        // Drop old approved column
        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn('approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            // Re-add approved boolean
            $table->boolean('approved')->default(false)->after('has_ctf_skins');
        });

        // Migrate data back
        DB::statement("UPDATE models SET approved = CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END");

        // Drop approval_status column
        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });
    }
};
