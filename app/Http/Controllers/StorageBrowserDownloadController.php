<?php

namespace App\Http\Controllers;

use App\Models\ServerDemo;
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
 * - The file is streamed from its storage straight into the HTTP response;
 *   NOTHING is ever written to local disk, so no watcher/worker (e.g. the
 *   public demo pipeline) can accidentally pick a private serverdemo up.
 *   That holds for the B2 mirror too - the bucket is private and its url is
 *   never handed out, the bytes come through here.
 * - Flysystem path normalisation rejects '..' traversal outside the disk
 *   root, and the `disk` parameter only accepts the two whitelisted names;
 *   the B2 mirror is reachable as a fallback for a serverdemo, never by
 *   asking for it.
 */
class StorageBrowserDownloadController extends Controller
{
    /**
     * disk name => moderator permissions that may pull from it, any one of
     * which is enough.
     *
     * record_flags is on the serverdemo list because the evidence panel on a
     * flagged record hands out links to individual demos, and someone
     * resolving reports needs to open them without being given the run of the
     * whole archive. That is not a hole: these urls are signed server-side,
     * so holding the permission lets you follow a link you were given, not
     * mint one for a path of your choosing. Browsing still needs
     * serverdemos_browser, which gates the page itself.
     */
    private const DISKS = [
        'serverdemos' => ['serverdemos_browser', 'record_flags'],
        'dl_storage' => ['storage_browser'],
    ];

    /** rclone mirrors /var/lib/serverdemos into this prefix of the bucket. */
    private const B2_PREFIX = 'serverdemos/';

    /**
     * Where a serverdemo may be read from, best guess first.
     *
     * The demos live on the storage VPS and are mirrored to B2, and the point
     * of the index is that the local copy will not be permanent. `on_contabo`
     * says which one to reach for, but it is only a hint: it is refreshed by
     * a scheduled walk, so it can be minutes stale in either direction.
     *
     * So both are tried rather than switched between. Whichever the index
     * expects goes first, and a wrong guess costs one failed open instead of
     * a 404 on a file that plainly exists.
     */
    private function candidates(string $diskName, string $path): array
    {
        if ($diskName !== 'serverdemos') {
            return [[$diskName, $path]];
        }

        $local = ['serverdemos', $path];
        $mirror = ['serverdemos_b2', self::B2_PREFIX . $path];

        $demo = ServerDemo::where('path', $path)->first(['on_contabo']);

        return $demo && ! $demo->on_contabo
            ? [$mirror, $local]
            : [$local, $mirror];
    }

    public function __invoke(Request $request)
    {
        $diskName = (string) $request->query('disk');
        $permissions = self::DISKS[$diskName] ?? null;
        abort_if($permissions === null, 404);

        $user = $request->user();
        $allowed = $user !== null && collect($permissions)
            ->contains(fn (string $permission) => $user->hasModeratorPermission($permission));
        abort_unless($allowed, 403);

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

        // Open the stream as the single source of truth for existence +
        // readability. We deliberately DON'T gate on a separate exists()
        // stat first: on the serverdemos disk the per-user directory is a
        // symlink into the uploader's home, and an SFTP stat() through that
        // symlink can report "not found" for a file the recursive listing
        // found and readStream() opens fine - which 404'd every serverdemos
        // download. readStream() is the operation that actually matters and
        // still fails closed (throws -> not a resource -> 404) for a file
        // that genuinely isn't there. Opening before the response starts
        // also means a failure is a clean 404, not a half-sent body.
        //
        // A serverdemo has two homes, so each candidate is tried in turn -
        // see candidates() for the order and why it is not a hard switch.
        $stream = null;
        foreach ($this->candidates($diskName, $path) as [$candidateDisk, $candidatePath]) {
            try {
                $stream = Storage::disk($candidateDisk)->readStream($candidatePath);
            } catch (\Throwable) {
                $stream = null;
            }

            if (is_resource($stream)) {
                break;
            }
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
