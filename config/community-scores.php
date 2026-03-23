<?php

return [
    'weights' => [
        'demos_uploaded' => 5,
        'tags_added' => 1,
        'alias_reports' => 3,
        'demo_assignment_reports' => 3,
        'maplists_created' => 3,
        'maplist_maps_added' => 2,
        'maplist_likes_received' => 1,
        'maplist_favorites_received' => 1,
        'play_later_maps' => 1,
        'marketplace_listings' => 3,
        'marketplace_reviews_written' => 3,
        'marketplace_reviews_received' => 1,
        'headhunter_created' => 5,
        'headhunter_completed' => 5,
        'record_flags' => 3,
        'models_uploaded' => 5,
        'render_requests' => 2,
        'clan_created' => 3,
        'clan_membership' => 1,
        'nsfw_flags' => 2,
        'records_count' => 0.1,
        'maps_authored' => 5,
        'models_authored' => 5,
        'social_connections' => 1,
        'profile_avatar' => 2,
        'profile_background' => 2,
        'profile_layout_customized' => 1,
        'name_effect_set' => 1,
        'avatar_effect_set' => 1,
        'donation_total_eur' => 0.5,
    ],

    'play_later_max_points' => 10,

    'tiers' => [
        ['name' => 'Defragger', 'key' => 'common', 'min_score' => 10, 'color' => '#9d9d9d'],       // Gray (Poor)
        ['name' => 'Defragger', 'key' => 'uncommon', 'min_score' => 50, 'color' => '#1eff00'],      // Green (Uncommon)
        ['name' => 'Defragger', 'key' => 'rare', 'min_score' => 150, 'color' => '#0070dd'],         // Blue (Rare)
        ['name' => 'Defragger', 'key' => 'epic', 'min_score' => 500, 'color' => '#a335ee'],         // Purple (Epic)
        ['name' => 'Defragger', 'key' => 'legendary', 'min_score' => 1500, 'color' => '#ff8000'],   // Orange (Legendary)
    ],

    'cache_ttl' => 43200, // 12 hours
];
