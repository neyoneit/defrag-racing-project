<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RenderedVideo;
use App\Models\Record;
use App\Models\UploadedDemo;
use App\Models\SiteSetting;
use App\Services\DemoProcessorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
            // Non-auto sources are uploaded as public, mark as published immediately
            'published_at' => $renderedVideo->source !== 'auto' ? now() : null,
            'publish_approved' => $renderedVideo->source !== 'auto',
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
            'status' => 'failed',
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
            'source' => 'required|string|in:discord,web,auto,migration',
            'requested_by' => 'nullable|string',
            'published_at' => 'nullable|date',
            'publish_approved' => 'nullable|boolean',
            'record_id' => 'nullable|integer',
            'demo_id' => 'nullable|integer',
        ]);

        // Check for duplicate by youtube_video_id
        $existing = RenderedVideo::where('youtube_video_id', $validated['youtube_video_id'])->first();
        if ($existing) {
            return response()->json(['success' => true, 'id' => $existing->id, 'duplicate' => true]);
        }

        // Try to match to an existing record
        $recordId = $validated['record_id'] ?? null;
        if (!$recordId && $validated['map_name'] && ($validated['physics'] ?? null) && ($validated['time_ms'] ?? null)) {
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
            'demo_id' => $validated['demo_id'] ?? null,
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
            'published_at' => $validated['published_at'] ?? now(),
            'publish_approved' => $validated['publish_approved'] ?? true,
        ]);

        return response()->json(['success' => true, 'id' => $video->id]);
    }

    public function uploadDemo(Request $request)
    {
        $request->validate([
            'demo' => 'required|file|max:524288',
            'discord_author' => 'nullable|string',
            'discord_message_id' => 'nullable|string',
            'discord_channel_id' => 'nullable|string',
        ]);

        $file = $request->file('demo');
        $originalFilename = $file->getClientOriginalName();

        // Check duplicate by hash
        $hash = md5_file($file->getRealPath());
        $existing = UploadedDemo::where('file_hash', $hash)
            ->whereNotIn('status', ['failed', 'failed-validity', 'unsupported-version'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'duplicate' => true,
                'demo_id' => $existing->id,
                'map_name' => $existing->map_name,
                'player_name' => $existing->player_name,
                'physics' => $existing->physics,
                'time_ms' => $existing->time_ms,
                'gametype' => $existing->gametype,
                'record_id' => $existing->record_id,
            ]);
        }

        // Create UploadedDemo record
        $demo = UploadedDemo::create([
            'original_filename' => $originalFilename,
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'status' => 'uploaded',
            'source' => 'demome',
        ]);

        // Store file locally
        $tempDir = "demos/temp/{$demo->id}";
        $storedPath = $file->storeAs($tempDir, $originalFilename);

        $demo->update(['file_path' => $storedPath]);

        // Dispatch processing job (async - compression, Backblaze upload, matching)
        \App\Jobs\ProcessDemoJob::dispatch($demo);

        // Synchronously parse metadata for immediate response
        try {
            $processSingleScript = base_path('app/Services/DemoProcessor/bin/process_single_demo.py');
            $fullPath = storage_path('app/' . $storedPath);

            $process = new \Symfony\Component\Process\Process(
                ['python3', '-W', 'ignore', $processSingleScript, $fullPath, '--json'],
                dirname($processSingleScript),
            );
            $process->setTimeout(60);
            $process->run();

            $output = trim($process->getOutput());
            $jsonData = json_decode($output, true);

            if ($jsonData && isset($jsonData['map_name'])) {
                $physicsParts = explode('.', strtoupper($jsonData['physics'] ?? ''));
                $physics = $physicsParts[1] ?? ($physicsParts[0] ?? null);

                return response()->json([
                    'success' => true,
                    'demo_id' => $demo->id,
                    'map_name' => $jsonData['map_name'],
                    'player_name' => $jsonData['player_name'] ?? null,
                    'physics' => $physics,
                    'time_ms' => isset($jsonData['time_seconds']) ? (int)($jsonData['time_seconds'] * 1000) : null,
                    'gametype' => $physicsParts[0] ?? null,
                    'record_id' => null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Demome upload: sync parse failed for {$originalFilename}: {$e->getMessage()}");
        }

        // Fallback: parse from filename
        $metadata = $this->parseFilename($originalFilename);

        return response()->json([
            'success' => true,
            'demo_id' => $demo->id,
            'map_name' => $metadata['map_name'],
            'player_name' => $metadata['player_name'],
            'physics' => $metadata['physics'],
            'time_ms' => $metadata['time_ms'],
            'gametype' => $metadata['gametype'],
            'record_id' => null,
        ]);
    }

    public function downloadDemo(UploadedDemo $demo)
    {
        if (empty($demo->file_path)) {
            return response()->json(['error' => 'Demo file not available'], 404);
        }

        $filename = $demo->processed_filename ?: $demo->original_filename;

        // Check if stored locally or on Backblaze
        $isLocal = str_starts_with($demo->file_path, 'demos/temp/') ||
                   str_starts_with($demo->file_path, 'demos/failed/');

        if ($isLocal) {
            $fullPath = storage_path("app/{$demo->file_path}");
            if (!file_exists($fullPath)) {
                return response()->json(['error' => 'Demo file not found'], 404);
            }
            $contents = file_get_contents($fullPath);
        } else {
            try {
                $contents = Storage::get($demo->file_path);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to retrieve demo from storage'], 500);
            }
        }

        if (!$contents) {
            return response()->json(['error' => 'Empty demo file'], 404);
        }

        // Try to extract from 7z archive
        $extracted = $this->extractFromArchive($contents, $filename);
        if ($extracted) {
            return response()->streamDownload(function() use ($extracted) {
                echo $extracted['contents'];
            }, $extracted['filename'], [
                'Content-Type' => 'application/octet-stream',
            ]);
        }

        // Return raw file
        return response()->streamDownload(function() use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function extractFromArchive($contents, $filename)
    {
        // Check for 7z magic bytes
        if (strlen($contents) < 6 || substr($contents, 0, 6) !== "7z\xBC\xAF\x27\x1C") {
            return null;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'demo_');
        file_put_contents($tempFile, $contents);

        $tempDir = $tempFile . '_extracted';
        mkdir($tempDir);

        try {
            $process = new \Symfony\Component\Process\Process(
                ['7z', 'x', '-o' . $tempDir, '-y', $tempFile]
            );
            $process->setTimeout(30);
            $process->run();

            if ($process->isSuccessful()) {
                $files = glob($tempDir . '/*');
                if (!empty($files)) {
                    $extractedFile = $files[0];
                    $extractedContents = file_get_contents($extractedFile);
                    $extractedFilename = basename($extractedFile);

                    // Clean up
                    array_map('unlink', glob($tempDir . '/*'));
                    rmdir($tempDir);
                    unlink($tempFile);

                    return [
                        'contents' => $extractedContents,
                        'filename' => $extractedFilename,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to extract 7z archive: {$e->getMessage()}");
        }

        // Clean up on failure
        if (is_dir($tempDir)) {
            array_map('unlink', glob($tempDir . '/*'));
            @rmdir($tempDir);
        }
        @unlink($tempFile);

        return null;
    }

    public function videosToPublish()
    {
        $videos = RenderedVideo::where('status', 'completed')
            ->where('publish_approved', true)
            ->whereNull('published_at')
            ->whereNotNull('youtube_video_id')
            ->select('id', 'youtube_video_id', 'map_name', 'player_name')
            ->limit(50)
            ->get();

        return response()->json(['videos' => $videos]);
    }

    public function markPublished(RenderedVideo $renderedVideo)
    {
        $renderedVideo->update(['published_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function lookupByHash(string $hash)
    {
        $demo = UploadedDemo::where('file_hash', $hash)
            ->whereNotIn('status', ['failed', 'failed-validity', 'unsupported-version'])
            ->first();

        if (!$demo) {
            return response()->json(['success' => false, 'message' => 'No demo found with this hash']);
        }

        // Check if there's already a RenderedVideo for this demo
        $existingVideo = RenderedVideo::where('demo_id', $demo->id)
            ->where('status', 'completed')
            ->first();

        // Also check by record_id if no demo_id match
        if (!$existingVideo && $demo->record_id) {
            $existingVideo = RenderedVideo::where('record_id', $demo->record_id)
                ->where('status', 'completed')
                ->first();
        }

        $response = [
            'success' => true,
            'demo_id' => $demo->id,
            'map_name' => $demo->map_name,
            'player_name' => $demo->player_name,
            'physics' => $demo->physics,
            'time_ms' => $demo->time_ms,
            'gametype' => $demo->gametype,
            'record_id' => $demo->record_id,
            'demo_filename' => $demo->processed_filename ?? $demo->original_filename,
            'download_url' => "https://defrag.racing/demos/{$demo->id}/download",
        ];

        if ($existingVideo) {
            $response['existing_video'] = [
                'id' => $existingVideo->id,
                'youtube_video_id' => $existingVideo->youtube_video_id,
                'youtube_url' => $existingVideo->youtube_url,
                'source' => $existingVideo->source,
            ];
        }

        return response()->json($response);
    }

    public function swapVideo(RenderedVideo $renderedVideo, Request $request)
    {
        $validated = $request->validate([
            'youtube_url' => 'required|string',
            'youtube_video_id' => 'required|string|max:20',
            'published_at' => 'nullable|date',
        ]);

        $oldYoutubeId = $renderedVideo->youtube_video_id;

        $renderedVideo->update([
            'youtube_url' => $validated['youtube_url'],
            'youtube_video_id' => $validated['youtube_video_id'],
            'published_at' => $validated['published_at'] ?? $renderedVideo->published_at,
        ]);

        return response()->json([
            'success' => true,
            'old_youtube_video_id' => $oldYoutubeId,
        ]);
    }

    private function parseFilename(string $filename): array
    {
        $result = [
            'map_name' => null,
            'player_name' => null,
            'physics' => null,
            'time_ms' => null,
            'gametype' => null,
        ];

        // Pattern: mapname[gametype.physics]mm.ss.mmm(player.country).dm_68
        if (preg_match('/([^[]+)\[([^.]+)\.([^\]]+)\](\d+)\.(\d+)\.(\d+)\(([^.]+)/', $filename, $m)) {
            $result['map_name'] = $m[1];
            $result['gametype'] = $m[2];
            $result['physics'] = strtoupper($m[3]);
            $result['time_ms'] = ((int)$m[4] * 60000) + ((int)$m[5] * 1000) + (int)$m[6];
            $result['player_name'] = $m[7];
        }

        return $result;
    }
}
