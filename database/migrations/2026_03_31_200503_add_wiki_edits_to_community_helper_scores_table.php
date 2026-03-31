<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_helper_scores', function (Blueprint $table) {
            $table->unsignedInteger('wiki_edits')->default(0)->after('nsfw_flags');
        });
    }

    public function down(): void
    {
        Schema::table('community_helper_scores', function (Blueprint $table) {
            $table->dropColumn('wiki_edits');
        });
    }
};
