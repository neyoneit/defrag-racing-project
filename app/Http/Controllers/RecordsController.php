<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Map;
use App\Models\Record;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecordsController extends Controller
{
    const PER_PAGE = 30;
    const PREBUILT_PAGES = 3; // Pages 1-3 are long-cached and incrementally updated

    // Cache keys pattern: records:page:{physics}:{mode}:{page}
    // Cache keys for counts: records:count:{mode}

    public function index(Request $request) {
        $mode = $request->input('mode', 'all');
        $vq3Page = max(1, (int) $request->input('vq3_page', 1));
        $cpmPage = max(1, (int) $request->input('cpm_page', 1));

        // Get counts (cached, incremented/decremented on record create/delete)
        $counts = $this->getCachedCounts($mode);

        // Get records for each physics
        $vq3Records = $this->getCachedPage('vq3', $mode, $vq3Page, $counts['vq3']);
        $cpmRecords = $this->getCachedPage('cpm', $mode, $cpmPage, $counts['cpm']);

        return Inertia::render('RecordsView')
            ->with('vq3Records', $vq3Records)
            ->with('cpmRecords', $cpmRecords);
    }

    /**
     * Get cached counts per physics for a given mode.
     * These are long-lived (12h) and incrementally updated by Record model events.
     */
    private function getCachedCounts(string $mode): array
    {
        $cacheKey = "records:count:{$mode}";

        return Cache::remember($cacheKey, 43200, function () use ($mode) {
            return [
                'vq3' => $this->buildModeQuery($mode)->where('physics', 'vq3')->count(),
                'cpm' => $this->buildModeQuery($mode)->where('physics', 'cpm')->count(),
            ];
        });
    }

    /**
     * Get a cached page of records.
     * Page 1: cached for 12h, updated incrementally when new records arrive.
     * Pages 2+: cached for 5 min (rarely visited, shift naturally).
     */
    private function getCachedPage(string $physics, string $mode, int $page, int $total): \Illuminate\Pagination\LengthAwarePaginator
    {
        // Pages 1-3: long-cached, incrementally updated on new records
        // Pages 4+: long-cached, invalidated on new records (lazy-loaded on first visit)
        $cacheKey = "records:page:{$physics}:{$mode}:{$page}";
        $items = Cache::remember($cacheKey, 43200, function () use ($physics, $mode, $page) {
            return $this->fetchPageFromDb($physics, $mode, $page);
        });

        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $pageName = $physics === 'vq3' ? 'vq3_page' : 'cpm_page';

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            self::PER_PAGE,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Fetch 30 records from DB with relations, serialized for caching.
     */
    private function fetchPageFromDb(string $physics, string $mode, int $page = 1): array
    {
        $recordColumns = ['id', 'name', 'country', 'mdd_id', 'mapname', 'rank', 'time', 'date_set', 'physics'];

        $records = $this->buildModeQuery($mode)
            ->where('physics', $physics)
            ->with(['user', 'map' => fn($q) => $q->select('name', 'thumbnail')])
            ->orderBy('date_set', 'DESC')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE)
            ->get($recordColumns);

        return $records->toArray();
    }

    /**
     * Build base query with mode filter applied.
     */
    private function buildModeQuery(string $mode)
    {
        $query = Record::query();

        if ($mode == 'run') {
            $query->where('mode', 'run');
        } elseif ($mode == 'ctf') {
            $query->where('mode', 'LIKE', 'ctf%');
        } elseif (in_array($mode, ['ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'])) {
            $query->where('mode', $mode);
        }

        return $query;
    }

    /**
     * Prepend a new record to all relevant page-1 caches.
     * Called from Record model boot() on created event.
     */
    public static function prependToCache(Record $record): void
    {
        // Load relations needed for display
        $record->load(['user', 'map' => fn($q) => $q->select('name', 'thumbnail')]);
        $recordArray = $record->only(['id', 'name', 'country', 'mdd_id', 'mapname', 'rank', 'time', 'date_set', 'physics']);
        $recordArray['user'] = $record->user?->toArray();
        $recordArray['map'] = $record->map?->toArray();

        $physics = $record->physics;

        // Determine which mode caches this record belongs to
        $modes = ['all'];
        if (str_starts_with($record->mode, 'ctf')) {
            $modes[] = 'ctf';
            $modes[] = $record->mode; // e.g. ctf1
        } else {
            $modes[] = $record->mode; // e.g. run
        }

        foreach ($modes as $mode) {
            // Cascade through prebuilt pages: prepend to page 1,
            // overflow from page 1 goes to page 2, overflow from 2 to 3, etc.
            $overflow = $recordArray;

            for ($p = 1; $p <= self::PREBUILT_PAGES; $p++) {
                $cacheKey = "records:page:{$physics}:{$mode}:{$p}";
                $items = Cache::get($cacheKey);

                if ($items === null || !is_array($items)) {
                    break; // Cache not built yet, stop cascading
                }

                // Prepend overflow item from previous page
                array_unshift($items, $overflow);

                // Pop last item as overflow for next page
                if (count($items) > self::PER_PAGE) {
                    $overflow = array_pop($items);
                } else {
                    $overflow = null;
                }

                Cache::put($cacheKey, $items, 43200);

                if ($overflow === null) {
                    break; // No overflow, no need to cascade further
                }
            }

            // Invalidate pages beyond prebuilt range (they shifted)
            for ($p = self::PREBUILT_PAGES + 1; $p <= self::PREBUILT_PAGES + 3; $p++) {
                Cache::forget("records:page:{$physics}:{$mode}:{$p}");
            }

            // Increment count
            $countKey = "records:count:{$mode}";
            $counts = Cache::get($countKey);
            if ($counts !== null && is_array($counts)) {
                $counts[$physics] = ($counts[$physics] ?? 0) + 1;
                Cache::put($countKey, $counts, 43200);
            }
        }
    }

    /**
     * Handle record deletion - decrement counts and invalidate page caches.
     */
    public static function removeFromCache(Record $record): void
    {
        $physics = $record->physics;

        $modes = ['all'];
        if (str_starts_with($record->mode, 'ctf')) {
            $modes[] = 'ctf';
            $modes[] = $record->mode;
        } else {
            $modes[] = $record->mode;
        }

        foreach ($modes as $mode) {
            // Decrement count
            $countKey = "records:count:{$mode}";
            $counts = Cache::get($countKey);
            if ($counts !== null && is_array($counts)) {
                $counts[$physics] = max(0, ($counts[$physics] ?? 0) - 1);
                Cache::put($countKey, $counts, 43200);
            }

            // Invalidate prebuilt pages + a few beyond (record removal shifts everything)
            for ($p = 1; $p <= self::PREBUILT_PAGES + 3; $p++) {
                Cache::forget("records:page:{$physics}:{$mode}:{$p}");
            }
        }
    }

    /**
     * Full cache rebuild - called by scheduled command every 12h.
     */
    public static function rebuildCache(): void
    {
        $controller = new self();
        $modes = ['all', 'run', 'ctf', 'ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'];

        foreach ($modes as $mode) {
            // Rebuild counts
            $countKey = "records:count:{$mode}";
            $counts = [
                'vq3' => $controller->buildModeQuery($mode)->where('physics', 'vq3')->count(),
                'cpm' => $controller->buildModeQuery($mode)->where('physics', 'cpm')->count(),
            ];
            Cache::put($countKey, $counts, 43200);

            // Rebuild pages 1-PREBUILT_PAGES for each physics
            foreach (['vq3', 'cpm'] as $physics) {
                for ($p = 1; $p <= self::PREBUILT_PAGES; $p++) {
                    $pageKey = "records:page:{$physics}:{$mode}:{$p}";
                    $items = $controller->fetchPageFromDb($physics, $mode, $p);
                    Cache::put($pageKey, $items, 43200);
                }
            }
        }

        \Log::info('Records cache fully rebuilt');
    }

    /**
     * Search records for demo reporting
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $timeFilter = $request->input('time', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = Record::query()
            ->with('user:id,name')
            ->where('mapname', 'LIKE', "{$query}%");

        if (!empty($timeFilter) && is_numeric($timeFilter)) {
            $results->where('time', '<=', (int)$timeFilter);
        }

        $results = $results
            ->orderBy('mapname', 'ASC')
            ->orderBy('time', 'ASC')
            ->orderBy('physics', 'ASC')
            ->orderBy('mode', 'ASC')
            ->limit(500)
            ->get(['id', 'user_id', 'mapname', 'time', 'physics', 'mode', 'gametype', 'date_set']);

        if ($results->isEmpty()) {
            $results = Record::query()
                ->with('user:id,name')
                ->whereHas('user', function ($userQuery) use ($query) {
                    $userQuery->where('name', 'LIKE', "%{$query}%");
                });

            if (!empty($timeFilter) && is_numeric($timeFilter)) {
                $results->where('time', '<=', (int)$timeFilter);
            }

            $results = $results
                ->orderBy('mapname', 'ASC')
                ->orderBy('time', 'ASC')
                ->orderBy('physics', 'ASC')
                ->orderBy('mode', 'ASC')
                ->limit(500)
                ->get(['id', 'user_id', 'mapname', 'time', 'physics', 'mode', 'gametype', 'date_set']);
        }

        return response()->json($results);
    }
}
