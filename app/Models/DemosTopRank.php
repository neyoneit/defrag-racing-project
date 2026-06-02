<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Materialized row of a map's unified Demos Top field. See the
 * create_demos_top_ranks migration and DemosTopRankService for how it's built.
 */
class DemosTopRank extends Model
{
    /**
     * At most the 3 oldest entries sharing an identical time in a group are
     * auto-render eligible (the rest are treated as redundant duplicates).
     */
    const MAX_IDENTICAL_TIME = 3;

    protected $fillable = [
        'map_name',
        'group_gametype',
        'physics',
        'physics_pattern',
        'entry_type',
        'record_id',
        'uploaded_demo_id',
        'time_ms',
        'date_set',
        'rank',
        'group_total',
        'grouped_count',
        'is_representative',
        'auto_render_eligible',
    ];

    protected $casts = [
        'time_ms' => 'integer',
        'rank' => 'integer',
        'group_total' => 'integer',
        'grouped_count' => 'integer',
        'is_representative' => 'boolean',
        'auto_render_eligible' => 'boolean',
        'date_set' => 'datetime',
    ];
}
