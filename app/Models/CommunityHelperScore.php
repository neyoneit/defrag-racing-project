<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityHelperScore extends Model
{
    protected $guarded = [];

    protected $casts = [
        'clan_membership' => 'boolean',
        'profile_avatar' => 'boolean',
        'profile_background' => 'boolean',
        'profile_layout_customized' => 'boolean',
        'name_effect_set' => 'boolean',
        'avatar_effect_set' => 'boolean',
        'donation_total_eur' => 'decimal:2',
        'total_score' => 'decimal:2',
        'community_badge_score' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCommunityTierAttribute(): ?array
    {
        $score = (float) $this->community_badge_score;
        $tiers = config('community-scores.tiers');

        $tier = null;
        foreach ($tiers as $t) {
            if ($score >= $t['min_score']) {
                $tier = $t;
            }
        }

        return $tier;
    }
}
