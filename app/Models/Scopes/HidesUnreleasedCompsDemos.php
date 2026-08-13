<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Hides a comps entry everywhere until its round has finished.
 *
 * A global scope rather than a filter added to each query on purpose:
 * uploaded_demos is read from a dozen places - the demos page, profiles, the
 * map page's Demos Top, the launcher API, headhunter, community tasks - and
 * one of them forgetting the condition would leak exactly the thing the round
 * depends on keeping quiet. Default-hidden means a new reader written next
 * year is safe without its author knowing comps exists.
 *
 * Anything that genuinely must see them - an admin reviewing a report, the
 * uploader's own list - takes the scope off deliberately, which is a decision
 * somebody has to write down rather than one they can forget to make.
 */
class HidesUnreleasedCompsDemos implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();

        $builder->where(function (Builder $q) use ($table) {
            $q->whereNull("{$table}.comps_hidden_until")
                ->orWhere("{$table}.comps_hidden_until", '<=', now());
        });
    }
}
