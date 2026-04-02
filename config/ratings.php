<?php

return [
    // Logistic curve parameters for map score calculation
    // score = 1000 * (A + (-A / (1 + Q * exp(-B * (reltime - M)))^(1/V)))
    'cfg_a' => (float) env('RATING_CFG_A', 1.2),
    'cfg_b' => (float) env('RATING_CFG_B', 1.33),
    'cfg_m' => (float) env('RATING_CFG_M', 0.3),
    'cfg_v' => (float) env('RATING_CFG_V', 0.1),
    'cfg_q' => (float) env('RATING_CFG_Q', 0.5),

    // Exponential decay for player rating weighted average
    // weight = exp(-D * rank), lower D = more equal weighting
    'cfg_d' => (float) env('RATING_CFG_D', 0.0001),

    // Hill/Logistic map multiplier: F(x) = (L * x^n) / (k^n + x^n)
    'mult_l' => (float) env('RATING_MULT_L', 1.0),
    'mult_n' => (float) env('RATING_MULT_N', 2.0),

    // Map eligibility thresholds
    'min_map_players' => (int) env('RATING_MIN_MAP_PLAYERS', 5),
    'min_top1_time' => (int) env('RATING_MIN_TOP1_TIME', 500),
    'max_tied_wr_players' => (int) env('RATING_MAX_TIED_WR', 3),

    // Player penalty threshold (fewer records = proportional penalty)
    'min_total_records' => (int) env('RATING_MIN_TOTAL_RECORDS', 10),
];
