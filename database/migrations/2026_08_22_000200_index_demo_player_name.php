<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The player filter reads `player_name`, so it needs an index.
 *
 * There are two name columns on a demo and only one of them is usable. The
 * indexed `q3df_login_name` is filled in on 8 226 rows; `player_name`, the
 * name written inside the demo itself, is filled in on 366 444 of 369 391 and
 * holds 7 102 distinct players. Filtering it read the whole table, 445 ms per
 * query, and counting the distinct names for the picker took 519 ms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->index('player_name', 'idx_demos_player_name');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropIndex('idx_demos_player_name');
        });
    }
};
