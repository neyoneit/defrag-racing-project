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
            // Record notification preview settings
            $table->string('preview_records')->default('all')->after('records_cpm'); // all, wr, none

            // System notification preview settings (JSON array of enabled types)
            // Default: ['announcement', 'clan', 'tournament'] - announcement is mandatory
            $table->json('preview_system')->nullable()->after('preview_records');

            // Add clan_notifications as separate from invitations
            $table->boolean('clan_notifications')->default(true)->after('tournament_news');
        });

        // Set default values for preview_system
        \DB::table('users')->update([
            'preview_system' => json_encode(['announcement', 'clan', 'tournament'])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['preview_records', 'preview_system', 'clan_notifications']);
        });
    }
};
