<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceCreatorProfile;
use App\Models\Map;
use Illuminate\Http\Request;

class MarketplaceSettingsController extends Controller
{
    public function getCreatorProfile()
    {
        $profile = MarketplaceCreatorProfile::where('user_id', auth()->id())->first();

        if (!$profile) {
            return response()->json([
                'is_listed' => true,
                'accepting_commissions' => true,
                'specialties' => [],
                'bio' => '',
                'rate_maps' => '',
                'rate_models' => '',
                'featured_map_ids' => [],
                'portfolio_urls' => [],
                'featured_maps' => [],
            ]);
        }

        $featuredMaps = $profile->featuredMaps()->select('id', 'name', 'thumbnail', 'author')->get();

        return response()->json([
            ...$profile->toArray(),
            'featured_maps' => $featuredMaps,
        ]);
    }

    public function updateCreatorProfile(Request $request)
    {
        $validated = $request->validate([
            'is_listed' => 'boolean',
            'accepting_commissions' => 'boolean',
            'specialties' => 'array',
            'specialties.*' => 'in:map,player_model,weapon_model,shadow_model',
            'bio' => 'nullable|string|max:2000',
            'rate_maps' => 'nullable|string|max:255',
            'rate_models' => 'nullable|string|max:255',
            'featured_map_ids' => 'array|max:5',
            'featured_map_ids.*' => 'integer|exists:maps,id',
            'portfolio_urls' => 'array|max:5',
            'portfolio_urls.*' => 'nullable|url|max:500',
        ]);

        // Filter out empty portfolio URLs
        if (isset($validated['portfolio_urls'])) {
            $validated['portfolio_urls'] = array_values(array_filter($validated['portfolio_urls']));
        }

        MarketplaceCreatorProfile::updateOrCreate(
            ['user_id' => auth()->id()],
            $validated
        );

        return response()->json(['success' => true]);
    }

    public function searchMapsForFeatured(Request $request)
    {
        $search = $request->input('search', '');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $maps = Map::where('name', 'like', "%{$search}%")
            ->select('id', 'name', 'thumbnail', 'author')
            ->limit(20)
            ->get();

        return response()->json($maps);
    }
}
