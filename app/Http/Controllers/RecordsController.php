<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Map;
use App\Models\Record;

class RecordsController extends Controller
{
    public function index(Request $request) {
        $physics = $request->input('physics', 'all');
        $mode = $request->input('mode', 'all');

        $records = Record::query();

        if ($physics == 'cpm' || $physics == 'vq3') {
            $records = $records->where('physics', $physics);
        }

        if ($mode == 'run') {
            $records = $records->where('mode', 'run');
        } elseif ($mode == 'ctf') {
            $records = $records->where('mode', 'LIKE', 'ctf%');
        }

        $records = $records->with('user')->with('map')->orderBy('date_set', 'DESC')->paginate(50)->withQueryString();

        return Inertia::render('RecordsView')
            ->with('records', $records);
    }
}
