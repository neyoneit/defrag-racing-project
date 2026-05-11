<?php

namespace App\Http\Controllers;

use App\Models\SavedMapFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SavedMapFilterController extends Controller
{
    private const MAX_PER_USER = 50;

    public function index()
    {
        $userId = Auth::id();
        $filters = SavedMapFilter::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'filter_state', 'created_at']);

        return response()->json(['filters' => $filters]);
    }

    public function store(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('saved_map_filters')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            // filter_state is the raw queries object from the sidebar form.
            // We don't validate its shape — MapFilters on the read side
            // tolerates missing/unknown keys, and filters drift over time.
            'filter_state' => ['required', 'array'],
        ]);

        // Cap to prevent runaway storage per user.
        if (SavedMapFilter::where('user_id', $userId)->count() >= self::MAX_PER_USER) {
            return response()->json([
                'message' => 'Maximum number of saved filters reached (' . self::MAX_PER_USER . '). Delete an old one first.',
            ], 422);
        }

        $filter = SavedMapFilter::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'filter_state' => $data['filter_state'],
        ]);

        return response()->json([
            'filter' => $filter->only(['id', 'name', 'filter_state', 'created_at']),
        ], 201);
    }

    public function destroy(SavedMapFilter $savedMapFilter)
    {
        if ($savedMapFilter->user_id !== Auth::id()) {
            abort(403);
        }

        $savedMapFilter->delete();

        return response()->json(['ok' => true]);
    }
}
