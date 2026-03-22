<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->boolean('is_ranked_vq3')->default(false)->after('visible');
            $table->boolean('is_ranked_cpm')->default(false)->after('is_ranked_vq3');
        });
    }

    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->dropColumn(['is_ranked_vq3', 'is_ranked_cpm']);
        });
    }
};
