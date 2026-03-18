<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('avatar_effects_intensity')->default(100)->after('avatar_border_color');
            $table->unsignedTinyInteger('name_effects_intensity')->default(100)->after('avatar_effects_intensity');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_effects_intensity', 'name_effects_intensity']);
        });
    }
};
