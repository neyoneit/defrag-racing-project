<?php

use App\Models\RatingSetting;

// Read from DB (rating_settings table), fall back to hardcoded defaults
// DB values are managed via admin panel: /defraghq/rating-settings

$defaults = [
    'cfg_a' => 1.2,
    'cfg_b' => 1.33,
    'cfg_m' => 0.3,
    'cfg_v' => 0.1,
    'cfg_q' => 0.5,
    'cfg_d' => 0.02,
    'mult_l' => 1.0,
    'mult_n' => 2.0,
    'min_map_players' => 5,
    'min_top1_time' => 500,
    'max_tied_wr_players' => 3,
    'rank_exponent' => 1.5,
    'min_total_records' => 10,
];

try {
    $db = RatingSetting::allAsArray();
} catch (\Throwable $e) {
    $db = [];
}

return [
    'cfg_a' => (float) ($db['cfg_a'] ?? $defaults['cfg_a']),
    'cfg_b' => (float) ($db['cfg_b'] ?? $defaults['cfg_b']),
    'cfg_m' => (float) ($db['cfg_m'] ?? $defaults['cfg_m']),
    'cfg_v' => (float) ($db['cfg_v'] ?? $defaults['cfg_v']),
    'cfg_q' => (float) ($db['cfg_q'] ?? $defaults['cfg_q']),
    'cfg_d' => (float) ($db['cfg_d'] ?? $defaults['cfg_d']),
    'mult_l' => (float) ($db['mult_l'] ?? $defaults['mult_l']),
    'mult_n' => (float) ($db['mult_n'] ?? $defaults['mult_n']),
    'min_map_players' => (int) ($db['min_map_players'] ?? $defaults['min_map_players']),
    'min_top1_time' => (int) ($db['min_top1_time'] ?? $defaults['min_top1_time']),
    'max_tied_wr_players' => (int) ($db['max_tied_wr_players'] ?? $defaults['max_tied_wr_players']),
    'rank_exponent' => (float) ($db['rank_exponent'] ?? $defaults['rank_exponent']),
    'min_total_records' => (int) ($db['min_total_records'] ?? $defaults['min_total_records']),
];
