<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RenderedVideo;
use App\Models\Record;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DemomeController extends Controller
{
    public function queue()
    {
        $paused = SiteSetting::getBool('demome:paused', false);

        // When paused, only serve force-render items (priority -1)
        $query = RenderedVideo::where('status', 'pending');
        if ($paused) {
            $query->where('priority', -1);
        }

        $items = $query->orderBy('priority', 'asc')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'demo_url' => $item->demo_url,
                    'demo_filename' => $item->demo_filename,
                    'map_name' => $item->map_name,
                    'player_name' => $item->player_name,
                    'physics' => $item->physics,
                    'time_ms' => $item->time_ms,
                    'priority' => $item->priority,
                    'source' => $item->source,
                    'record_id' => $item->record_id,
                    'map_page_url' => 'https://defrag.racing/maps/' . $item->map_name,
                ];
            });

        return response()->json([
            'paused' => $paused,
            'items' => $items,
        ]);
    }

    public function claim(RenderedVideo $renderedVideo)
    {
        if ($renderedVideo->status !== 'pending') {
            return response()->json(['error' => 'Item already claimed or processed'], 409);
        }

        $updated = DB::table('rendered_videos')
            ->where('id', $renderedVideo->id)
            ->where('status', 'pending')
            ->update(['status' => 'rendering', 'updated_at' => now()]);

        if (!$updated) {
            return response()->json(['error' => 'Item already claimed'], 409);
        }

        Cache::put('demome:current_status', 'rendering', now()->addMinutes(30));
        Cache::put('demome:current_video_id', $renderedVideo->id, now()->addMinutes(30));

        return response()->json(['success' => true]);
    }

    public function complete(RenderedVideo $renderedVideo, Request $request)
    {
        $validated = $request->validate([
            'youtube_url' => 'required|string',
            'youtube_video_id' => 'required|string|max:20',
            'render_duration_seconds' => 'nullable|integer',
            'video_file_size' => 'nullable|integer',
        ]);

        $renderedVideo->update([
            'status' => 'completed',
            'youtube_url' => $validated['youtube_url'],
            'youtube_video_id' => $validated['youtube_video_id'],
            'render_duration_seconds' => $validated['render_duration_seconds'] ?? null,
            'video_file_size' => $validated['video_file_size'] ?? null,
        ]);

        Cache::put('demome:current_status', 'idle', now()->addMinutes(30));
        Cache::forget('demome:current_video_id');

        return response()->json(['success' => true]);
    }

    public function fail(RenderedVideo $renderedVideo, Request $request)
    {
        $validated = $request->validate([
            'failure_reason' => 'required|string',
        ]);

        $renderedVideo->update([
            'failure_reason' => $validated['failure_reason'],
            'retry_count' => $renderedVideo->retry_count + 1,
            'status' => $renderedVideo->retry_count < 2 ? 'pending' : 'failed',
        ]);

        Cache::put('demome:current_status', 'idle', now()->addMinutes(30));
        Cache::forget('demome:current_video_id');

        return response()->json(['success' => true, 'will_retry' => $renderedVideo->retry_count < 3]);
    }

    public function heartbeat(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:idle,rendering,uploading',
            'current_video_id' => 'nullable|integer',
        ]);

        Cache::put('demome:last_heartbeat', now()->toISOString(), now()->addMinutes(10));
        Cache::put('demome:current_status', $validated['status'], now()->addMinutes(10));

        if ($validated['current_video_id']) {
            Cache::put('demome:current_video_id', $validated['current_video_id'], now()->addMinutes(30));
        } else {
            Cache::forget('demome:current_video_id');
        }

        return response()->json([
            'success' => true,
            'paused' => SiteSetting::getBool('demome:paused', false),
        ]);
    }

    public function report(Request $request)
    {
        $validated = $request->validate([
            'map_name' => 'required|string',
            'player_name' => 'required|string',
            'physics' => 'nullable|string',
            'time_ms' => 'nullable|integer',
            'gametype' => 'nullable|string',
            'demo_url' => 'required|string',
            'demo_filename' => 'nullable|string',
            'youtube_url' => 'required|string',
            'youtube_video_id' => 'required|string|max:20',
            'render_duration_seconds' => 'nullable|integer',
            'video_file_size' => 'nullable|integer',
            'source' => 'required|string|in:discord,web,auto',
            'requested_by' => 'nullable|string',
        ]);

        // Try to match to an existing record
        $recordId = null;
        if ($validated['map_name'] && $validated['physics'] && $validated['time_ms']) {
            $record = Record::where('mapname', $validated['map_name'])
                ->where('physics', $validated['physics'])
                ->where('time', $validated['time_ms'])
                ->first();

            if ($record) {
                $recordId = $record->id;
            }
        }

        $video = RenderedVideo::create([
            'map_name' => $validated['map_name'],
            'player_name' => $validated['player_name'],
            'physics' => $validated['physics'],
            'time_ms' => $validated['time_ms'],
            'gametype' => $validated['gametype'] ?? null,
            'record_id' => $recordId,
            'source' => $validated['source'],
            'requested_by' => $validated['requested_by'],
            'status' => 'completed',
            'priority' => 3,
            'demo_url' => $validated['demo_url'],
            'demo_filename' => $validated['demo_filename'],
            'youtube_url' => $validated['youtube_url'],
            'youtube_video_id' => $validated['youtube_video_id'],
            'render_duration_seconds' => $validated['render_duration_seconds'],
            'video_file_size' => $validated['video_file_size'],
            'is_visible' => true,
        ]);

        return response()->json(['success' => true, 'id' => $video->id]);
    }
}
