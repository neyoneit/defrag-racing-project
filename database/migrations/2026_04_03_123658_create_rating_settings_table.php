<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed with defaults
        $settings = [
            ['key' => 'cfg_a', 'value' => '1.2', 'description' => 'Logistic curve amplitude'],
            ['key' => 'cfg_b', 'value' => '1.33', 'description' => 'Logistic curve steepness'],
            ['key' => 'cfg_m', 'value' => '0.3', 'description' => 'Logistic curve midpoint offset'],
            ['key' => 'cfg_v', 'value' => '0.1', 'description' => 'Logistic curve asymmetry (Richards)'],
            ['key' => 'cfg_q', 'value' => '0.5', 'description' => 'Logistic curve Q parameter'],
            ['key' => 'cfg_d', 'value' => '0.02', 'description' => 'Exponential decay for weighted average (lower = more equal)'],
            ['key' => 'mult_l', 'value' => '1.0', 'description' => 'Hill function max multiplier (L)'],
            ['key' => 'mult_n', 'value' => '2.0', 'description' => 'Hill function steepness (n)'],
            ['key' => 'min_map_players', 'value' => '5', 'description' => 'Min players for map to be ranked'],
            ['key' => 'min_top1_time', 'value' => '500', 'description' => 'Min WR time in ms (filter trivial maps)'],
            ['key' => 'max_tied_wr_players', 'value' => '3', 'description' => 'Max players sharing WR time (filter free WR maps)'],
            ['key' => 'rank_exponent', 'value' => '1.5', 'description' => 'Rank multiplier exponent'],
            ['key' => 'min_total_records', 'value' => '10', 'description' => 'Min records before penalty applies'],
        ];

        foreach ($settings as $setting) {
            DB::table('rating_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_settings');
    }
};
