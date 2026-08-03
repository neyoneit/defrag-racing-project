<?php

namespace App\Http\Controllers;

use App\Models\RecordFlag;
use App\Models\ServerDemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Hands a validator the ONE serverdemo they are entitled to.
 *
 * Deliberately different from StorageBrowserDownloadController: there the
 * caller names a path and the permission decides whether they may read it.
 * Here the caller names a REPORT, and the path is looked up from it - a
 * validator can never ask for a demo, only for the demo of a report that was
 * handed to them. There is no path parameter to tamper with.
 *
 * Three things must all hold, and they are checked on every hit rather than
 * only when the link is minted:
 * - the report was cleared by an admin and is still open,
 * - this user is the one holding it, or it has been opened to everyone,
 * - the run actually has a serverdemo.
 */
class ServerdemoValidationDownloadController extends Controller
{
    /** rclone mirrors /var/lib/serverdemos into this prefix of the bucket. */
    private const B2_PREFIX = 'serverdemos/';

    public function __invoke(Request $request, RecordFlag $flag)
    {
        $user = $request->user();
        abort_unless($user?->hasModeratorPermission('serverdemo_validation'), 403);

        // The gate. Without an admin clearing the report there is nothing to
        // download, no matter who is asking.
        abort_unless($flag->admin_cleared_at !== null, 403);

        // Entitlement follows the CASE, not the individual report: a
        // validator takes on a player and gets everything reported against
        // them, which is the point of grouping in the first place.
        $case = $flag->validationCase;
        abort_if($case === null, 403);
        abort_unless($case->isOpen() || $user->isAdmin(), 403);

        $entitled = $user->isAdmin()
            || $case->assigned_to_user_id === $user->id
            || in_array($case->validation_stage, ['all_validators', 'admin'], true);
        abort_unless($entitled, 403);

        $demo = $flag->serverDemo();
        abort_if($demo === null, 404);

        $stream = $this->open($demo);
        abort_unless(is_resource($stream), 404);

        // Chunked and flushed: Octane/Swoole caps a single buffered body and
        // answers 502 when a Content-Length accompanies chunked writes.
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
        }, basename($demo->path), [
            'Content-Type' => 'application/octet-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * A serverdemo has two homes - the storage VPS and the B2 mirror - and
     * `on_contabo` is a scheduled guess, so both are tried in the order it
     * suggests rather than switched between.
     */
    private function open(ServerDemo $demo)
    {
        $candidates = $demo->on_contabo
            ? [['serverdemos', $demo->path], ['serverdemos_b2', self::B2_PREFIX . $demo->path]]
            : [['serverdemos_b2', self::B2_PREFIX . $demo->path], ['serverdemos', $demo->path]];

        foreach ($candidates as [$disk, $path]) {
            try {
                $stream = Storage::disk($disk)->readStream($path);
            } catch (\Throwable) {
                $stream = null;
            }

            if (is_resource($stream)) {
                return $stream;
            }
        }

        return null;
    }
}
