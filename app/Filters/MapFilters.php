<?php

namespace App\Filters;

use Illuminate\Http\Request;
use App\Models\Map;
use App\Models\Record;
use App\Models\User;

use Illuminate\Database\Eloquent\Builder;

class MapFilters {
    protected $queries = [];

    public function filter(Request $request) {
        $maps = Map::select('id', 'name', 'author', 'pk3', 'thumbnail', 'physics', 'gametype', 'weapons', 'items', 'functions', 'is_nsfw', 'date_added', 'created_at', 'cpm_average', 'vq3_average')
            ->withAvg('difficultyRatings', 'rating')
            ->withCount('difficultyRatings');

        $maps = $this->applySort($request, $maps);

        $maps = $this->search($request, $maps);
        $maps = $this->author($request, $maps);
        $maps = $this->tags($request, $maps);
        $maps = $this->difficulty($request, $maps);

        $maps = $this->physics($request, $maps);
        $maps = $this->gametype($request, $maps);

        $maps = $this->haveRecords($request, $maps);
        $maps = $this->haveNoRecords($request, $maps);
        $maps = $this->worldRecord($request, $maps);

        $maps = $this->hasIncludeWeapons($request, $maps);
        $maps = $this->hasExcludeWeapons($request, $maps);

        $maps = $this->hasIncludeFunctions($request, $maps);
        $maps = $this->hasExcludeFunctions($request, $maps);

        $maps = $this->hasIncludeItems($request, $maps);
        $maps = $this->hasExcludeItems($request, $maps);

        $maps = $this->recordsCount($request, $maps);

        $maps = $this->averageLength($request, $maps);

        $maps = $this->rankThreshold($request, $maps);

        return [
            'query'     =>      $maps,
            'data'      =>      $this->queries
        ];
    }

    private function applySort(Request $request, $maps) {
        $sort = $request->input('sort', 'newest');
        $this->queries['sort'] = $sort;

        switch ($sort) {
            case 'oldest':
                $maps = $maps->orderBy('date_added', 'ASC')->orderBy('id', 'ASC');
                break;
            case 'most_records':
                $maps = $maps->withCount('records')->orderBy('records_count', 'DESC');
                break;
            case 'name_asc':
                $maps = $maps->orderBy('name', 'ASC');
                break;
            case 'newest':
            default:
                $maps = $maps->orderBy('date_added', 'DESC')->orderBy('id', 'DESC');
                break;
        }

        return $maps;
    }

    private function buildFuzzyLike(string $input): string {
        $chars = mb_str_split(strtolower(trim($input)));
        return '%' . implode('%', $chars) . '%';
    }

    public function search(Request $request, $maps) {
        if ($request->filled('search')) {
            $fuzzy = $this->buildFuzzyLike($request->search);
            $maps = $maps->where('name', 'LIKE', $fuzzy);
            $this->queries['search'] = $request->search;
        }

        return $maps;
    }

    public function author(Request $request, $maps) {
        if ($request->filled('author')) {
            $fuzzy = $this->buildFuzzyLike($request->author);
            $maps = $maps->where('author', 'LIKE', $fuzzy);
            $this->queries['author'] = $request->author;
        }

        return $maps;
    }

    public function tags(Request $request, $maps) {
        if ($request->filled('tags')) {
            $tagNames = explode(',', $request->tags);
            $tagNames = array_map('trim', $tagNames);
            $tagNames = array_map('strtolower', $tagNames);

            // Filter maps that have ALL specified tags
            foreach ($tagNames as $tagName) {
                $maps = $maps->whereHas('tags', function (Builder $query) use ($tagName) {
                    $query->where('name', $tagName);
                });
            }

            $this->queries['tags'] = $request->tags;
        }

        return $maps;
    }

    public function difficulty(Request $request, $maps) {
        if ($request->filled('difficulty') && count($request->difficulty) > 0) {
            $levels = array_map('intval', $request->difficulty);
            $maps = $maps->whereHas('difficultyRatings', function (Builder $query) use ($levels) {
                $query->selectRaw('1')
                    ->groupBy('map_id')
                    ->havingRaw('ROUND(AVG(rating)) IN (' . implode(',', array_fill(0, count($levels), '?')) . ')', $levels);
            });

            $this->queries['difficulty'] = $request->difficulty;
        }

        return $maps;
    }

    public function physics(Request $request, $maps) {
        if ($request->filled('physics')) {
            if (count($request->physics) == 1) {
                $maps = $maps->where(function (Builder $query) use($request) {
                    $query->where('physics', trim($request->physics[0]))
                        ->orWhere('physics', 'all');
                });
            }
            $this->queries['physics'] = $request->physics;
        }

        return $maps;
    }

    public function gametype(Request $request, $maps) {
        if ($request->filled('gametype')) {
            if (count($request->gametype) > 0) {
                $maps = $maps->whereIn('gametype', $request->gametype);
            }
            $this->queries['gametype'] = $request->gametype;
        }

        return $maps;
    }

    public function haveRecords(Request $request, $maps) {
        if ($request->filled('has_records') && count($request->has_records) > 0) {
            $maps = $maps->whereHas('records', function (Builder $query) use ($request) {
                $query = $query->whereIn('mdd_id', $request->has_records)
                    ->groupBy('mapname')
                    ->havingRaw('COUNT(DISTINCT mdd_id) = ?', [count($request->has_records)]);
            });

            $this->queries['has_records'] = $request->has_records;
        }

        return $maps;
    }

    public function haveNoRecords(Request $request, $maps) {
        if ($request->filled('have_no_records') && count($request->have_no_records) > 0) {
            $maps = $maps->whereDoesntHave('records', function (Builder $query) use ($request) {
                $query->whereIn('mdd_id', $request->have_no_records);
            });

            $this->queries['have_no_records'] = $request->have_no_records;
        }

        return $maps;
    }

    public function worldRecord(Request $request, $maps) {
        if ($request->filled('world_record') && count($request->world_record) > 0) {
            $maps = $maps->whereHas('records', function (Builder $query) use ($request) {
                $mddId = $request->world_record[0];
                
                $query->where('mdd_id', $mddId)
                    ->where('time', '>', 0)
                    ->where(function ($subquery) {
                        $subquery->where(function ($subsubquery) {
                            $subsubquery->where('physics', 'cpm')
                                         ->where('time', function ($minSubquery) {
                                             $minSubquery->selectRaw('MIN(time)')
                                                         ->from('records')
                                                         ->whereColumn('mapname', 'maps.name')
                                                         ->where('physics', 'cpm');
                                         });
                        })
                        ->orWhere(function ($subsubquery) {
                            $subsubquery->where('physics', 'vq3')
                                         ->where('time', function ($minSubquery) {
                                             $minSubquery->selectRaw('MIN(time)')
                                                         ->from('records')
                                                         ->whereColumn('mapname', 'maps.name')
                                                         ->where('physics', 'vq3');
                                         });
                        });
                    });
            });

            $this->queries['world_record'] = $request->world_record;
        }

        return $maps;
    }

    public function hasIncludeWeapons(Request $request, $maps) {
        if ($request->filled('weapons') && isset($request->weapons['include'])) {
            foreach($request->weapons['include'] as $weapon) {
                $maps = $maps->where('weapons', 'LIKE', '%' . $weapon . '%');
            }

            $this->queries['weapons']['include'] = $request->weapons['include'];
        }

        return $maps;
    }

    public function hasExcludeWeapons(Request $request, $maps) {
        if ($request->filled('weapons') && isset($request->weapons['exclude'])) {
            foreach($request->weapons['exclude'] as $weapon) {
                $maps = $maps->where('weapons', 'NOT LIKE', '%' . $weapon . '%');
            }

            $this->queries['weapons']['exclude'] = $request->weapons['exclude'];
        }

        return $maps;
    }

    public function hasIncludeFunctions(Request $request, $maps) {
        if ($request->filled('functions') && isset($request->functions['include'])) {
            foreach($request->functions['include'] as $function) {
                $maps = $maps->where('functions', 'LIKE', '%' . $function . '%');
            }

            $this->queries['functions']['include'] = $request->functions['include'];
        }

        return $maps;
    }

    public function hasExcludeFunctions(Request $request, $maps) {
        if ($request->filled('functions') && isset($request->functions['exclude'])) {
            foreach($request->functions['exclude'] as $function) {
                $maps = $maps->where('functions', 'NOT LIKE', '%' . $function . '%');
            }

            $this->queries['functions']['exclude'] = $request->functions['exclude'];
        }

        return $maps;
    }

    public function hasIncludeItems(Request $request, $maps) {
        if ($request->filled('items') && isset($request->items['include'])) {
            foreach($request->items['include'] as $item) {
                $maps = $maps->where('items', 'LIKE', '%' . $item . '%');
            }

            $this->queries['items']['include'] = $request->items['include'];
        }

        return $maps;
    }

    public function hasExcludeItems(Request $request, $maps) {
        if ($request->filled('items') && isset($request->items['exclude'])) {
            foreach($request->items['exclude'] as $item) {
                $maps = $maps->where('items', 'NOT LIKE', '%' . $item . '%');
            }

            $this->queries['items']['exclude'] = $request->items['exclude'];
        }

        return $maps;
    }

    public function recordsCount(Request $request, $maps) {
        if ($request->filled('records_count') && count($request->records_count) == 2) {
            if (! is_numeric($request->records_count[0]) || ! is_numeric($request->records_count[1])) {
                return $maps;
            }

            if ($request->records_count[0] > $request->records_count[1]) {
                return $maps;
            }

            $min = (int) $request->records_count[0];
            $max = (int) $request->records_count[1];

            // Determine physics filter for record counting
            $physics = null;
            if ($request->filled('physics') && count($request->physics) == 1) {
                $physics = trim($request->physics[0]);
            }

            if ($min == 0 && $max == 0) {
                // Maps with zero records (in selected physics or overall)
                $maps = $maps->whereDoesntHave('records', function (Builder $query) use ($physics) {
                    if ($physics) {
                        $query->where('physics', $physics);
                    }
                });
            } elseif ($min == 0) {
                // Include maps with 0 records OR maps with records count <= max
                $maps = $maps->where(function ($query) use ($physics, $max) {
                    $query->whereDoesntHave('records', function (Builder $q) use ($physics) {
                        if ($physics) {
                            $q->where('physics', $physics);
                        }
                    })->orWhereHas('records', function (Builder $q) use ($physics, $max) {
                        if ($physics) {
                            $q->where('physics', $physics);
                        }
                        $q->groupBy('mapname')
                            ->havingRaw('COUNT(DISTINCT mdd_id) <= ?', [$max]);
                    });
                });
            } else {
                $maps = $maps->whereHas('records', function (Builder $query) use ($physics, $min, $max) {
                    if ($physics) {
                        $query->where('physics', $physics);
                    }
                    $query->groupBy('mapname')
                        ->havingRaw('COUNT(DISTINCT mdd_id) BETWEEN ? AND ?', [$min, $max]);
                });
            }

            $this->queries['records_count'] = $request->records_count;
        }

        return $maps;
    }

    public function averageLength(Request $request, $maps) {
        if ($request->filled('average_length') && count($request->average_length) == 2) {
            if (! is_numeric($request->average_length[0]) || ! is_numeric($request->average_length[1])) {
                return $maps;
            }

            if ($request->average_length[0] > $request->average_length[1]) {
                return $maps;
            }

            $start = $request->average_length[0] * 1000;
            $end = $request->average_length[1] * 1000;

            $maps = $maps->whereRaw('(cpm_average + vq3_average) / 2 BETWEEN ? AND ?', [$start, $end]);

            $this->queries['average_length'] = $request->average_length;
        }

        return $maps;
    }

    public function rankThreshold(Request $request, $maps) {
        $rankMin = $request->input('rank_min', 1);
        $rankMax = $request->input('rank_max', 999);

        // Only apply filter if it's not the default range
        if ($rankMin > 1 || $rankMax < 999) {
            if (! is_numeric($rankMin) || ! is_numeric($rankMax)) {
                return $maps;
            }

            if ($rankMin > $rankMax) {
                return $maps;
            }

            // Filter maps based on player records within the rank range
            if ($request->filled('has_records') && count($request->has_records) > 0) {
                $maps = $maps->whereHas('records', function (Builder $query) use ($request, $rankMin, $rankMax) {
                    $query->whereIn('mdd_id', $request->has_records)
                        ->whereBetween('rank', [$rankMin, $rankMax]);
                });
            }

            $this->queries['rank_min'] = $rankMin;
            $this->queries['rank_max'] = $rankMax;
        }

        return $maps;
    }
}