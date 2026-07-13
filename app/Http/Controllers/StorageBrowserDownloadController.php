<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Download endpoint for the admin storage browsers (Serverdemos Browser +
 * Storage Browser). The Filament pages hand the browser a short-lived SIGNED
 * url to this route instead of streaming through Livewire - Livewire doesn't
 * recognise a hand-built StreamedResponse as a download (the button silently
 * did nothing), and streaming large files through its JSON payload is wrong
 * anyway.
 *
 * Security properties (deliberate):
 * - 'signed' middleware: the url expires (5 min) and can't be forged.
 * - auth + the SAME granular moderator permission the originating panel
 *   requires, re-checked here on every hit.
 * - The file is streamed SFTP -> HTTP response directly; NOTHING is ever
 *   written to local disk, so no watcher/worker (e.g. the public demo
 *   pipeline) can accidentally pick a private serverdemo up.
 * - Flysystem path normalisation rejects '..' traversal outside the disk
 *   root, and only the two whitelisted disks are reachable.
 */
class StorageBrowserDownloadController extends Controller
{
    /** disk name => moderator permission required to pull from it */
    private const DISKS = [
        'serverdemos' => 'serverdemos_browser',
        'dl_storage' => 'storage_browser',
    ];

    public function __invoke(Request $request)
    {
        $diskName = (string) $request->query('disk');
        $permission = self::DISKS[$diskName] ?? null;
        abort_if($permission === null, 404);
        abort_unless($request->user()?->hasModeratorPermission($permission) ?? false, 403);

        $path = trim((string) $request->query('path'), '/');
        abort_if($path === '', 404);

        // Reject traversal/degenerate segments outright instead of relying on
        // Flysystem's normalizer (which throws a 500 on '..' escapes). Demo
        // filenames contain brackets etc., so only the segment shape is
        // constrained, not the character set. Backslash is rejected too:
        // Flysystem rewrites '\' to '/' BEFORE resolving '..', so a segment
        // like '..\other' would otherwise slip past this loop and then
        // traverse.
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || str_contains($segment, '\\') || str_contains($segment, "\0")) {
                abort(404);
            }
        }

        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($path), 404);

        // Open the stream BEFORE the response starts: a failure here is a
        // clean 404 instead of a broken half-sent response.
        $stream = null;
        try {
            $stream = $disk->readStream($path);
        } catch (\Throwable) {
        }
        abort_unless(is_resource($stream), 404);

        // Octane/Swoole delivers streamed responses as chunked writes, so a
        // manual Content-Length alongside them produces an invalid response
        // (nginx answers 502), and Swoole's output buffer caps a single
        // buffered body at ~2 MB anyway. Stream in small chunks and flush
        // each one - no Content-Length, no temp file, bounded memory.
        return response()->streamDownload(function () use ($stream) {
            while (! feof($stream)) {
                $chunk = fread($stream, 64 * 1024);
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                flush();
            }
            fclose($stream);
        }, basename($path), [
            'Content-Type' => 'application/octet-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
