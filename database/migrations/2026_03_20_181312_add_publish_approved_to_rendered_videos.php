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
        Schema::table('rendered_videos', function (Blueprint $table) {
            $table->boolean('publish_approved')->default(false)->after('published_at');
        });

        // Mark already-published videos as approved
        DB::table('rendered_videos')
            ->whereNotNull('published_at')
            ->update(['publish_approved' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rendered_videos', function (Blueprint $table) {
            $table->dropColumn('publish_approved');
        });
    }
};
