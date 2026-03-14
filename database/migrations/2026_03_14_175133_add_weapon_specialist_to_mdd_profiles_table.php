<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mdd_profiles', function (Blueprint $table) {
            $table->string('weapon_specialist')->nullable()->after('vq3_bfg_records');
            $table->unsignedInteger('weapon_specialist_count')->default(0)->after('weapon_specialist');
        });
    }

    public function down(): void
    {
        Schema::table('mdd_profiles', function (Blueprint $table) {
            $table->dropColumn(['weapon_specialist', 'weapon_specialist_count']);
        });
    }
};
