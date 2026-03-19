<?php

namespace App\Http\Controllers;

use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class RenderRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'record_id' => 'required|exists:records,id',
            'demo_id' => 'required|exists:uploaded_demos,id',
        ]);

        $user = $request->user();

        // Rate limit: 20 requests per day
        $cacheKey = "render_requests_user_{$user->id}_" . now()->format('Y-m-d');
        $todayCount = Cache::get($cacheKey, 0);

        if ($todayCount >= 20) {
            return response()->json([
                'error' => 'Daily render limit reached (20/day)',
                'remaining' => 0,
            ], 429);
        }

        $demo = UploadedDemo::findOrFail($validated['demo_id']);
        $record = Record::findOrFail($validated['record_id']);

        // Check if already rendered, in queue, or failed (admin handles re-render)
        $existing = RenderedVideo::where('demo_id', $demo->id)
            ->whereIn('status', ['pending', 'rendering', 'uploading', 'completed', 'failed'])
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'This demo is already rendered or in queue',
                'status' => $existing->status,
                'youtube_url' => $existing->youtube_url,
            ], 409);
        }

        // Generate signed URL for demome to download the demo
        $demoUrl = URL::signedRoute('demos.download', ['demo' => $demo->id], now()->addDays(7));

        $video = RenderedVideo::create([
            'map_name' => $demo->map_name ?? $record->mapname,
            'player_name' => $demo->player_name ?? $record->name,
            'physics' => $demo->physics ?? $record->physics,
            'time_ms' => $demo->time_ms ?? $record->time,
            'gametype' => $demo->gametype ?? $record->gametype,
            'record_id' => $record->id,
            'demo_id' => $demo->id,
            'user_id' => $user->id,
            'source' => 'web',
            'requested_by' => $user->name,
            'status' => 'pending',
            'priority' => 0,
            'demo_url' => $demoUrl,
            'demo_filename' => $demo->original_filename,
        ]);

        // Increment daily counter
        Cache::put($cacheKey, $todayCount + 1, now()->endOfDay());

        $queuePosition = RenderedVideo::where('status', 'pending')
            ->where('id', '<', $video->id)
            ->count() + 1;

        return response()->json([
            'success' => true,
            'id' => $video->id,
            'queue_position' => $queuePosition,
            'remaining_today' => 20 - ($todayCount + 1),
        ]);
    }
}
