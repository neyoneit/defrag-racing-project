<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the old rank multiplier formula
 *   rank_mult = (((tp * v) - rank) / ((tp * v) - 1)) ^ n
 * with Batawi's generalized stretched exponential
 *   t = (rank - 1) / (tp - 1)
 *   rank_mult = k ^ (t ^ (n + p * (1 - t)))
 *
 * Three new params drive the new curve (k = floor for the worst rank,
 * n = overall steepness, p = position of the steep section). The two
 * old params (rank_exponent, rank_v) are dropped — they have no meaning
 * under the new formula.
 *
 * Filament admin and the rust service are updated in the same change
 * so the DB schema, the consumer, and the editor stay consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rating_settings')->whereIn('key', ['rank_exponent', 'rank_v'])->delete();

        $now = now();
        $rows = [
            ['key' => 'rank_k', 'value' => '0.80', 'description' => 'Rank multiplier — value for the worst-ranked record on the map (k in k^(t^(n+p(1-t)))).'],
            ['key' => 'rank_n', 'value' => '0.95', 'description' => 'Rank multiplier — controls overall curve steepness.'],
            ['key' => 'rank_p', 'value' => '0.05', 'description' => 'Rank multiplier — controls where the steep section sits along the curve.'],
        ];
        foreach ($rows as $row) {
            DB::table('rating_settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now]),
            );
        }
    }

    public function down(): void
    {
        DB::table('rating_settings')->whereIn('key', ['rank_k', 'rank_n', 'rank_p'])->delete();

        $now = now();
        $rows = [
            ['key' => 'rank_exponent', 'value' => '0.33', 'description' => 'Rank multiplier exponent (n).'],
            ['key' => 'rank_v', 'value' => '2.0', 'description' => 'Rank multiplier total_players modifier (v).'],
        ];
        foreach ($rows as $row) {
            DB::table('rating_settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now]),
            );
        }
    }
};
