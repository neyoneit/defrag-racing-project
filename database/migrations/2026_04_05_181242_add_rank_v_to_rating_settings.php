<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rating_settings')->insertOrIgnore([
            'key' => 'rank_v',
            'value' => '2.0',
            'description' => 'Rank multiplier total_players modifier (v)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rating_settings')->where('key', 'rank_v')->delete();
    }
};
