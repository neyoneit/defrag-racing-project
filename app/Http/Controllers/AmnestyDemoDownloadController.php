<?php

namespace App\Http\Controllers;

use App\Models\PlayerSelfReport;
use App\Models\ServerDemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The serverdemo of a withdrawn run, for an admin.
 *
 * Admins only, deliberately - not validators. A withdrawal is private, and the
 * demo of it says which run was withdrawn just as loudly as the row does.
 */
class AmnestyDemoDownloadController extends Controller
{
    /** rclone mirrors /var/lib/serverdemos into this prefix of the bucket. */
    private const B2_PREFIX = 'serverdemos/';

    public function __invoke(Request $request, PlayerSelfReport $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $demo = $report->serverDemo();
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
     * Same two homes and the same packed/unpacked ambiguity as the validation
     * download - see ServerdemoValidationDownloadController::open for why both
     * names are tried on both disks.
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
