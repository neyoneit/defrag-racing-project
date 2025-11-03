<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Map;
use App\Models\Record;

class RecordsController extends Controller
{
    public function index(Request $request) {
        $mode = $request->input('mode', 'all');
        $page = $request->input('page', 1);

        // Base query for mode filtering
        $baseQuery = function() use ($mode) {
            $query = Record::query();

            if ($mode == 'run') {
                $query->where('mode', 'run');
            } elseif ($mode == 'ctf') {
                $query->where('mode', 'LIKE', 'ctf%');
            } elseif (in_array($mode, ['ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'])) {
                $query->where('mode', $mode);
            }

            return $query;
        };

        // Get VQ3 records (50 per page)
        $vq3Records = $baseQuery()
            ->where('physics', 'vq3')
            ->with('user', 'map')
            ->orderBy('date_set', 'DESC')
            ->paginate(50, ['*'], 'vq3_page')
            ->withQueryString();

        // Get CPM records (50 per page)
        $cpmRecords = $baseQuery()
            ->where('physics', 'cpm')
            ->with('user', 'map')
            ->orderBy('date_set', 'DESC')
            ->paginate(50, ['*'], 'cpm_page')
            ->withQueryString();

        return Inertia::render('RecordsView')
            ->with('vq3Records', $vq3Records)
            ->with('cpmRecords', $cpmRecords);
    }

    /**
     * Search records for demo reporting
     * API endpoint for finding records by map, player, time
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $timeFilter = $request->input('time', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Search by mapname first (faster, indexed)
        $results = Record::query()
            ->with('user:id,name')
            ->where('mapname', 'LIKE', "{$query}%");

        // Apply time filter if provided
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

        // If no results by mapname, try player name search
        if ($results->isEmpty()) {
            $results = Record::query()
                ->with('user:id,name')
                ->whereHas('user', function ($userQuery) use ($query) {
                    $userQuery->where('name', 'LIKE', "%{$query}%");
                });

            // Apply time filter to player search as well
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
