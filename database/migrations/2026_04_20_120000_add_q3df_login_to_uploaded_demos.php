<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->string('q3df_login_name', 64)->nullable()->after('player_name');
            $table->string('q3df_login_name_colored', 128)->nullable()->after('q3df_login_name');
            $table->string('match_method', 32)->nullable()->after('name_confidence');

            $table->index('q3df_login_name', 'uploaded_demos_q3df_login_idx');
            $table->index('q3df_login_name_colored', 'uploaded_demos_q3df_login_colored_idx');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropIndex('uploaded_demos_q3df_login_idx');
            $table->dropIndex('uploaded_demos_q3df_login_colored_idx');
            $table->dropColumn(['q3df_login_name', 'q3df_login_name_colored', 'match_method']);
        });
    }
};
