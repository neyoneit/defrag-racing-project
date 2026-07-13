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

        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($path), 404);

        $size = null;
        try {
            $size = $disk->size($path);
        } catch (\Throwable) {
        }

        return response()->streamDownload(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, basename($path), array_filter([
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $size !== null ? (string) $size : null,
            'X-Accel-Buffering' => 'no',
        ]));
    }
}
