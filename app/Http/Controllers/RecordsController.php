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
}
