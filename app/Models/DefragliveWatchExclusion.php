<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A contest exclusion ("ban") for a watched player - watch time they accrued
 * BEFORE `excluded_before` does not count toward DefragLive contest tickets or
 * the public watch stats. Time after that moment counts normally again, so a
 * legit player whose nick was abused by a cheater can still compete from the
 * ban onward. Created from the Filament admin with a written reason.
 *
 * Identity matches watch sessions the same way the leaderboard groups them:
 * by mdd_id when set, and/or by the color-stripped lowercase name.
 */
class DefragliveWatchExclusion extends Model
{
    protected $table = 'defraglive_watch_exclusions';

    protected $fillable = [
        'mdd_id',
        'name_clean',
        'player_name',
        'reason',
        'excluded_before',
    ];

    protected $casts = [
        'mdd_id' => 'integer',
        'excluded_before' => 'datetime',
    ];
}
