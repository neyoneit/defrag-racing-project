<?php

namespace App\Http\Controllers;

use App\Models\RenderedVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Carbon\Carbon;

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

        $videos = RenderedVideo::whereIn('status', ['completed', 'failed'])
            ->visible()
            ->orderBy('created_at', 'desc')
            ->select([
                'id', 'map_name', 'player_name', 'physics', 'time_ms', 'status',
                'youtube_url', 'youtube_video_id', 'source', 'requested_by',
                'render_duration_seconds', 'record_id', 'created_at',
            ])
            ->paginate(24)
            ->withQueryString();

        // Live queue: currently rendering + pending
        $currentlyRendering = RenderedVideo::where('status', 'rendering')
            ->with(['record' => fn($q) => $q->select('id', 'rank')])
            ->select(['id', 'map_name', 'player_name', 'physics', 'time_ms', 'source', 'requested_by', 'record_id', 'updated_at'])
            ->first();

        $pendingQueue = RenderedVideo::where('status', 'pending')
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'asc')
            ->with(['record' => fn($q) => $q->select('id', 'rank')])
            ->select(['id', 'map_name', 'player_name', 'physics', 'time_ms', 'priority', 'source', 'requested_by', 'record_id', 'created_at'])
            ->limit(10)
            ->get();

        $pendingTotal = RenderedVideo::where('status', 'pending')->count();

        $lastHeartbeat = Cache::get('demome:last_heartbeat');
        $demomeOnline = $lastHeartbeat && Carbon::parse($lastHeartbeat)->diffInMinutes(now()) < 5;

        return Inertia::render('Youtube', [
            'stats' => $stats,
            'videos' => $videos,
            'currentlyRendering' => $currentlyRendering,
            'pendingQueue' => $pendingQueue,
            'pendingTotal' => $pendingTotal,
            'demomeOnline' => $demomeOnline,
        ]);
    }
}
