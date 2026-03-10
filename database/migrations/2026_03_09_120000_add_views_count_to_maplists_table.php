<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maplists', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('favorites_count');
            $table->index(['is_public', 'views_count']);
        });
    }

    public function down(): void
    {
        Schema::table('maplists', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'views_count']);
            $table->dropColumn('views_count');
        });
    }
};
