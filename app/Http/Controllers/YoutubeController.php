<?php

namespace App\Http\Controllers;

use App\Models\RenderedVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class YoutubeController extends Controller
{
    public function index(Request $request)
    {
        $stats = Cache::remember('youtube:stats', 600, function () {
            return [
                'total_renders' => RenderedVideo::completed()->count(),
                'total_render_hours' => round(RenderedVideo::completed()->sum('render_duration_seconds') / 3600, 1),
                'total_maps' => RenderedVideo::completed()->visible()->distinct('map_name')->count('map_name'),
            ];
        });

        $videos = RenderedVideo::completed()
            ->visible()
            ->orderBy('created_at', 'desc')
            ->select([
                'id', 'map_name', 'player_name', 'physics', 'time_ms',
                'youtube_url', 'youtube_video_id', 'source', 'requested_by',
                'render_duration_seconds', 'record_id', 'created_at',
            ])
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Youtube', [
            'stats' => $stats,
            'videos' => $videos,
        ]);
    }
}
