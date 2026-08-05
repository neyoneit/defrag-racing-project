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

        // Reporting a run must never be a way to get the demo of it. Anyone
        // who reported this case, and anyone who ever shared a clan with a
        // reporter, is refused here as well as being skipped by the
        // assignment - otherwise the everyone-at-once stage would hand them
        // the demo anyway.
        abort_if(! $user->isAdmin() && in_array($user->id, $case->conflictedUserIds(), true), 403);

        $entitled = $user->isAdmin()
            || $case->assigned_to_user_id === $user->id
            || in_array($case->validation_stage, ['all_validators', 'admin'], true);
        abort_unless($entitled, 403);

        $demo = $flag->serverDemo();
        abort_if($demo === null, 404);

        $opened = $this->open($demo);
        abort_if($opened === null, 404);

        [$stream, $filename] = $opened;

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
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * A serverdemo has two homes - the storage VPS and the B2 mirror - and
     * `on_contabo` is a scheduled guess, so both are tried in the order it
     * suggests rather than switched between.
     *
     * The mirror only ships files younger than a few hours, so the ~116k demos
     * that were repacked in bulk on 2026-08-03 are in B2 under their original
     * `.dm_68` name while the index knows them as `.dm_68.7z`. Both names are
     * therefore tried on each disk: the same demo, and B2 legitimately holds
     * either form depending on whether it arrived before or after packing.
     *
     * Returns [stream, download name] so the name matches what was actually
     * opened - handing a raw demo out under a .7z name gives the moderator a
     * file nothing will open.
     */
    private function open(ServerDemo $demo): ?array
    {
        $paths = [$demo->path];

        if (str_ends_with($demo->path, '.7z')) {
            $paths[] = substr($demo->path, 0, -3);
        }

        $disks = $demo->on_contabo
            ? ['serverdemos', 'serverdemos_b2']
            : ['serverdemos_b2', 'serverdemos'];

        foreach ($disks as $disk) {
            foreach ($paths as $path) {
                $full = $disk === 'serverdemos_b2' ? self::B2_PREFIX . $path : $path;

                try {
                    $stream = Storage::disk($disk)->readStream($full);
                } catch (\Throwable) {
                    $stream = null;
                }

                if (is_resource($stream)) {
                    return [$stream, basename($path)];
                }
            }
        }

        return null;
    }
}
