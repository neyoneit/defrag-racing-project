<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0)->after('downloads');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('pinned_models')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn('views');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pinned_models');
        });
    }
};
