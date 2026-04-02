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
        Schema::table('community_helper_scores', function (Blueprint $table) {
            $table->unsignedInteger('community_tasks_completed')->default(0)->after('wiki_edits');
        });
    }

    public function down(): void
    {
        Schema::table('community_helper_scores', function (Blueprint $table) {
            $table->dropColumn('community_tasks_completed');
        });
    }
};
