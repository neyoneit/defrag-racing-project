<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('default_show_oldtop')->default(false)->after('avatar_border_color');
            $table->boolean('default_show_offline')->default(false)->after('default_show_oldtop');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['default_show_oldtop', 'default_show_offline']);
        });
    }
};
